<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class ProductsSampleExport implements  FromArray, WithHeadings,WithColumnWidths
{
     public function array(): array
    {
        return [
            ['SKU0001', 'Apple iPhone 15', 799, 'Flagship phone', 'iphone15.jpg'],
            ['SKU0002', 'Samsung Galaxy S24', 749, 'Android flagship', 'galaxy_s24.jpg'],
            ['SKU0003', 'Apple AirPods Pro', 199, 'Noise cancelling earbuds', 'airpods_pro.jpg'],
        ];
    }

      public function headings(): array
    {
        return ['sku', 'name', 'price', 'meta', 'image'];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, 
            'B' => 25, 
            'C' => 10,
            'D' => 35, 
            'E' => 20,
        ];
    }

}
