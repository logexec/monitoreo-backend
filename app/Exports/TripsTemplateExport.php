<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class TripsTemplateExport implements FromArray, WithHeadings, WithEvents
{
    /**
     * Define las cabeceras descriptivas para la plantilla.
     */
    public function headings(): array
    {
        return [
            'ID de viaje',
            'ID de viaje externo',
            'Fecha de entrega',
            'Nombre del chofer',
            'Email del chofer',
            'Teléfono del chofer',
            'Origen',
            'Destino',
            'Proyecto',
            'Número de placa',
            'Tipo de propiedad',
            'Jornada',
            'Proveedor de GPS',
        ];
    }

    /**
     * Como no vamos a incluir datos de ejemplo, devolvemos un array vacío.
     */
    public function array(): array
    {
        return [];
    }

    /**
     * Registramos eventos para darle formato a la hoja.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Establecer la fila 1 como título: fusionamos A1:M1 y centramos el texto
                $sheet->mergeCells('A1:M1');
                $sheet->setCellValue('A1', 'Plantilla para carga de viajes');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Mover la cabecera a la fila 2 (se desplaza automáticamente con WithHeadings)
                // Congelar la fila 2 para que la cabecera sea sticky
                $sheet->freezePane('A2');

                // Aplicar formato a las cabeceras (fila 2)
                $headerRange = 'A2:M2';
                $sheet->getStyle($headerRange)->getFont()->setBold(true);
                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE5E5E5');

                // Agregar validación a la columna "Jornada" (columna L) para que solo se permitan "dia" o "noche"
                // Se aplica desde la fila 3 hasta la 1000 (se puede ajustar el rango)
                for ($row = 3; $row <= 1000; $row++) {
                    $cell = "L{$row}";
                    $validation = $sheet->getCell($cell)->getDataValidation();
                    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(false);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setErrorTitle('Entrada inválida');
                    $validation->setError('Solo se permite "dia" o "noche"');
                    $validation->setFormula1('"dia,noche"');
                }
            },
        ];
    }
}
