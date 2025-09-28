<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulk Import + Chunked Upload</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
      body { font-family: Arial, sans-serif; padding: 20px; }
      .dropzone { border: 2px dashed #ccc; padding: 30px; text-align: center; }
      .file-list { margin-top: 10px; }
    </style>
</head>
<body>
<div style="max-width:800px;margin:auto;">
  <div style="padding:20px;border:1px solid #ccc;border-radius:10px;margin-bottom:20px;">
    <h1>Bulk CSV Import (Products)</h1>
    <form id="csvForm" enctype="multipart/form-data">
        <input type="file" name="csv" id="csv" accept=".csv" />
        <button type="submit" style="margin-left:10px;">Import CSV</button>
        {{-- <button type="button" style="margin-left:10px;">Download Sample CSV</button> --}}
        <a style="margin-left:10px;" href="{{ route('download.sample.products') }}" class="btn btn-success">Download Sample CSV</a>

    </form>
  </div>

  <div style="padding:20px;border:1px solid #ccc;border-radius:10px;">
    <h2>Chunked Drag-and-Drop Upload</h2>
    <div id="drop" class="dropzone" style="cursor:pointer;">Drop images here or click to select</div>
    <input type="file" id="fileInput" multiple style="display:none" />
    <div class="file-list" id="fileList"></div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-md5/2.19.0/js/md5.min.js"></script>
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
document.getElementById('csvForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = document.getElementById('csv').files[0];
    if (!f) { alert('Select CSV'); return; }
    const fd = new FormData();
    fd.append('csv', f);
    const resp = await fetch('/import/csv', { method: 'POST', body: fd, headers: {'X-CSRF-TOKEN': csrf} });
    const j = await resp.json();
    alert('Import summary:\n' + JSON.stringify(j.summary, null, 2));
});

// drag drop
const drop = document.getElementById('drop');
const fileInput = document.getElementById('fileInput');
drop.addEventListener('click', () => fileInput.click());
drop.addEventListener('dragover', e => { e.preventDefault(); drop.style.background='#f0f0f0'; });
drop.addEventListener('dragleave', e => { drop.style.background=''; });
drop.addEventListener('drop', e => { e.preventDefault(); drop.style.background=''; handleFiles(e.dataTransfer.files); });

fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

async function handleFiles(files) {
    for (const file of files) {
        const id = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2,8);
        const li = document.createElement('div');
        li.textContent = file.name + ' - 0%';
        document.getElementById('fileList').appendChild(li);
        await uploadFileChunked(file, id, li);
    }
}

// chunked upload implementation
async function uploadFileChunked(file, uploadId, statusEl) {
    const chunkSize = 1024 * 1024; // 1MB
    const totalChunks = Math.ceil(file.size / chunkSize);
    // check server for received chunks
    const check = await fetch('/upload/status/' + uploadId);
    const status = await check.json();
    const received = new Set(status.received || []);

    for (let i = 0; i < totalChunks; i++) {
        if (received.has(i)) continue;
        const start = i * chunkSize;
        const chunk = file.slice(start, start + chunkSize);
        const fd = new FormData();
        fd.append('chunk', chunk, file.name + '.part.' + i);
        fd.append('upload_id', uploadId);
        fd.append('chunk_index', i);
        fd.append('total_chunks', totalChunks);

        const res = await fetch('/upload/chunk', { method: 'POST', body: fd, headers: {'X-CSRF-TOKEN': csrf} });
        if (!res.ok) {
            statusEl.textContent = file.name + ' - upload failed at chunk ' + i;
            return;
        }
        statusEl.textContent = file.name + ' - ' + Math.round(((i+1)/totalChunks)*100) + '%';
    }

    // all chunks uploaded - compute checksum
    const arrayBuffer = await file.arrayBuffer();
    const digest = await crypto.subtle.digest('SHA-256', arrayBuffer);
    const md5hex = Array.from(new Uint8Array(digest)).map(b => b.toString(16).padStart(2,'0')).join('');

    // finalize
    const fdc = new FormData();
    fdc.append('upload_id', uploadId);
    fdc.append('filename', file.name);
    fdc.append('checksum', md5hex);
    // optionally pass entity_type and entity_key to attach
    // fdc.append('entity_type', 'product');
    // fdc.append('entity_key', 'SKU123');

    const comp = await fetch('/upload/complete', { method: 'POST', body: fdc, headers: {'X-CSRF-TOKEN': csrf} });
    const jc = await comp.json();
    if (comp.ok) {
        statusEl.textContent = file.name + ' - completed';
    } else {
        statusEl.textContent = file.name + ' - failed completion: ' + JSON.stringify(jc);
    }
}
</script>
</body>
</html>
