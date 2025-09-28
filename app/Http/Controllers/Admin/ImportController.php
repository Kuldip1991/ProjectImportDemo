<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Facades\Log;
use App\Models\{Product,Upload};
use App\Exports\ProductsSampleExport;
use Maatwebsite\Excel\Facades\Excel;
class ImportController extends Controller
{
    public function index(){
        return view('import.index');
    }

    public function import(Request $request)
    {
        $file = $request->file('csv');
        if (!$file || !$file->isValid()) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        $rowNumber = 0;
        $header = null;
        $summary = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'invalid' => 0,
            'duplicates' => 0,
            'invalid_rows' => []
        ];
        $seen = [];
        $requiredColumns = ['sku', 'name'];
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($rowNumber === 1) {
                $header = array_map(function ($h) {
                    return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)));
                }, $row);
                continue;
            }
            $summary['total']++;
            if (count($row) !== count($header)) {
                $summary['invalid']++;
                $summary['invalid_rows'][] = [
                    'row' => $rowNumber,
                    'error' => 'Column count mismatch'
                ];
                continue;
            }
            $data = array_map('trim', array_combine($header, $row));
            $missing = [];
            foreach ($requiredColumns as $col) {
                if (!isset($data[$col]) || $data[$col] === '') {
                    $missing[] = $col;
                }
            }
            if (!empty($missing)) {
                $summary['invalid']++;
                $summary['invalid_rows'][] = [
                    'row' => $rowNumber,
                    'missing_columns' => $missing
                ];
                continue;
            }
            $sku = $data['sku'];
            if (isset($seen[$sku])) {
                $summary['duplicates']++;
                continue;
            }
            $seen[$sku] = true;
            $existing = Product::where('sku', $sku)->first();
            $product_image = null;
            if (!empty($data['image'])) {
                $product_image = Upload::where('original_name', $data['image'])->first();
            }
            $payload = [
                'sku' => $sku,
                'name' => $data['name'],
                'price' => isset($data['price']) ? floatval($data['price']) : null,
                'meta' => json_encode($data),
                'primary_image_id' => $product_image->id ?? null
            ];
            if ($existing) {
                Product::where('sku', $sku)->update($payload);
                $summary['updated']++;
            } else {
                Product::insert($payload + ['created_at' => now(), 'updated_at' => now()]);
                $summary['imported']++;
            }
        }
        fclose($handle);
        return response()->json(['summary' => $summary]);
    }
    public function receiveChunk(Request $request)
    {
        $uploadId = $request->input('upload_id');
        $chunkIndex = intval($request->input('chunk_index'));
        $totalChunks = intval($request->input('total_chunks'));

        if (!$uploadId || !$request->hasFile('chunk')) {
            return response()->json(['error' => 'Missing parameters'], 400);
        }

        $chunk = $request->file('chunk');
        $dir = storage_path('app/uploads/' . $uploadId . '/chunks');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $chunk->move($dir, 'chunk_' . $chunkIndex);

        return response()->json(['status' => 'ok', 'received' => $chunkIndex]);
    }

    public function status($uploadId)
    {
        $dir = storage_path('app/uploads/' . $uploadId . '/chunks');
        $received = [];
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if (strpos($f, 'chunk_') === 0) {
                    $received[] = intval(str_replace('chunk_', '', $f));
                }
            }
        }

        return response()->json(['received' => $received]);
    }

    public function complete(Request $request)
    {
        $uploadId = $request->input('upload_id');
        $filename = $request->input('filename');
        $expectedChecksum = $request->input('checksum'); 
        $entity_type = $request->input('entity_type', 'product'); 
        $entity_key = $request->input('entity_key'); 
        $chunksDir = storage_path('app/uploads/' . $uploadId . '/chunks');
        $assembledDir = storage_path('app/uploads/' . $uploadId);
        $finalPath = $assembledDir . '/' . basename($filename);
        if (!is_dir($chunksDir)) {
            return response()->json(['error' => 'No chunks found'], 400);
        }
        $chunks = glob($chunksDir . '/chunk_*');
        natsort($chunks);
        $out = fopen($finalPath, 'wb');
        foreach ($chunks as $c) {
            $in = fopen($c, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);
        $actual = hash_file('sha256',$finalPath);
        if ($expectedChecksum && $actual !== $expectedChecksum) {
            unlink($finalPath);
            return response()->json(['error' => 'checksum_mismatch', 'expected' => $expectedChecksum, 'actual' => $actual], 400);
        }
        Image::configure(['driver' => 'gd']);
        $variants = [256, 512, 1024];
        $storedPaths = [];
        foreach ($variants as $size) {
            $img = Image::make($finalPath);
            $img->resize($size, $size, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $variantPath = 'public/images/' . $uploadId . "_{$size}." . pathinfo($filename, PATHINFO_EXTENSION);
            Storage::put($variantPath, (string) $img->encode());
            $storedPaths[$size] = $variantPath;
        }
        $uploadRecordId = DB::table('uploads')->insertGetId([
            'upload_id' => $uploadId,
            'original_name' => $filename,
            'checksum' => $actual,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $imageId = DB::table('images')->insertGetId([
            'upload_id' => $uploadRecordId,
            'path_256' => $storedPaths[256] ?? null,
            'path_512' => $storedPaths[512] ?? null,
            'path_1024' => $storedPaths[1024] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($entity_type === 'product' && $entity_key) {
            $product = DB::table('products')->where('sku', $entity_key)->first();
            if ($product) {
                if ($product->primary_image_id !== $imageId) {
                    DB::table('products')->where('id', $product->id)->update(['primary_image_id' => $imageId]);
                }
            }
        }

        return response()->json(['status' => 'completed', 'image_id' => $imageId, 'paths' => $storedPaths]);
    }

    public function downloadSampleExcel()
    {
        return Excel::download(new ProductsSampleExport, 'sample_products.xlsx');
    }

//     public function complete(Request $request)
// {
//     $uploadId = $request->input('upload_id');
//     $filename = $request->input('filename');
//     $expectedChecksum = strtolower($request->input('checksum', '')); // sha256 expected
//     $entity_type = $request->input('entity_type', 'product');
//     $entity_key = $request->input('entity_key');

//     $chunksDir = storage_path('app/uploads/' . $uploadId . '/chunks');
//     $assembledDir = storage_path('app/uploads/' . $uploadId);
//     $finalPath = $assembledDir . '/' . basename($filename);

//     if (!is_dir($chunksDir)) {
//         return response()->json(['error' => 'No chunks found'], 400);
//     }

//     // gather and sort chunks reliably
//     $chunks = glob($chunksDir . '/chunk_*');
//     if (!$chunks) {
//         return response()->json(['error' => 'No chunk files found'], 400);
//     }
//     natsort($chunks);
//     $chunks = array_values($chunks); // reindex after natsort

//     // ensure assembled dir exists
//     if (!is_dir($assembledDir)) {
//         mkdir($assembledDir, 0755, true);
//     }

//     // assemble
//     $out = fopen($finalPath, 'wb');
//     foreach ($chunks as $c) {
//         if (!is_file($c)) continue;
//         $in = fopen($c, 'rb');
//         stream_copy_to_stream($in, $out);
//         fclose($in);
//     }
//     fclose($out);

//     clearstatcache(true, $finalPath);
//     if (!is_file($finalPath) || filesize($finalPath) === 0) {
//         return response()->json(['error' => 'assembled_file_missing_or_empty', 'path' => $finalPath], 400);
//     }

//     // verify checksum (sha256)
//     $actual = strtolower(hash_file('sha256', $finalPath));
//     if ($expectedChecksum && $actual !== $expectedChecksum) {
//         // optionally keep final file for debugging, but we delete per your original flow
//         @unlink($finalPath);
//         return response()->json(['error' => 'checksum_mismatch', 'expected' => $expectedChecksum, 'actual' => $actual], 400);
//     }

//     // prepare storage
//     // ensure public disk 'images' folder exists
//     Storage::disk('public')->makeDirectory('images');

//     // choose extension (fallback to jpg)
//     $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg');
//     if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
//         $ext = 'jpg';
//     }

//     // use intervention with GD and respect EXIF orientation
//     Image::configure(['driver' => 'gd']);

//     $variants = [256, 512, 1024];
//     $storedPaths = [];

//     foreach ($variants as $size) {
//         // recreate image instance each iteration (avoid in-place mutation)
//         $img = Image::make($finalPath)->orientate();

//         // resize while maintaining aspect ratio (fit within size x size)
//         $img->resize($size, $size, function ($constraint) {
//             $constraint->aspectRatio();
//             $constraint->upsize();
//         });

//         // encode explicitly to original extension with quality
//         $encoded = (string) $img->encode($ext, 90);

//         $relPath = "images/{$uploadId}_{$size}.{$ext}"; // stored in storage/app/public/images/...
//         $ok = Storage::disk('public')->put($relPath, $encoded);

//         // debug log
//         Log::info('variant_written', ['upload' => $uploadId, 'size' => $size, 'path' => $relPath, 'ok' => $ok, 'exists' => Storage::disk('public')->exists($relPath)]);

//         // store public URL (assuming storage:link has been created)
//         $storedPaths[$size] = Storage::disk('public')->url($relPath);
//     }

//     // optional cleanup of chunks (uncomment if you want)
//     // foreach ($chunks as $c) { @unlink($c); }
//     // @rmdir($chunksDir);

//     // store upload & image record
//     $uploadRecordId = DB::table('uploads')->insertGetId([
//         'upload_id' => $uploadId,
//         'original_name' => $filename,
//         'checksum' => $actual,
//         'created_at' => now(),
//         'updated_at' => now(),
//     ]);

//     $imageId = DB::table('images')->insertGetId([
//         'upload_id' => $uploadRecordId,
//         'path_256' => $storedPaths[256] ?? null,
//         'path_512' => $storedPaths[512] ?? null,
//         'path_1024' => $storedPaths[1024] ?? null,
//         'created_at' => now(),
//         'updated_at' => now(),
//     ]);

//     // attach primary to entity (product by sku)
//     if ($entity_type === 'product' && $entity_key) {
//         $product = DB::table('products')->where('sku', $entity_key)->first();
//         if ($product) {
//             if ($product->primary_image_id !== $imageId) {
//                 DB::table('products')->where('id', $product->id)->update(['primary_image_id' => $imageId]);
//             }
//         }
//     }

//     return response()->json([
//         'status' => 'completed',
//         'image_id' => $imageId,
//         'paths' => $storedPaths,
//         'assembled' => $finalPath,
//         'filesize' => filesize($finalPath),
//     ]);
// }

}
