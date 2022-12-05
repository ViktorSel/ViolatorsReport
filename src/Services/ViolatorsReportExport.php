<?php

namespace Ast\ViolatorsReport\Services;

use Ast\ViolatorsReport\Http\Controllers\ViolatorsReportController;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Ui\Models\Employees;
use Ast\ViolatorsReport\Services\ViolatorsReportHelper;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ViolatorsReportExport extends Export
{
    CONST style_hor_center_ver_center = [
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
    ];
    CONST style_all_borders = [
        'borders'   => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'   => [ 'argb' => '00000000'],
            ],
        ],
    ];

    private $helper;
    private $date_year;
    private $section;
    private $plazas;

    private $spreadSheet;
    private $row = 9;


    public function __construct(array $params)
    {
        $this->spreadSheet = $this->open($this->getTemplateFilePath());
        $this->helper = app(ViolatorsReportHelper::class);
        $this->date_year = $params[ViolatorsReportController::ATTR_DATE];
        $this->section = $this->get_section($params[ViolatorsReportController::ATTR_SECTION]);
    }

    private function get_section($section_code):string
    {
        $section = DB::select(
        /** @lang PostgresSQL */'select "sectionDescription"  from lvl2pdb."sections" where "society" = ?;',
            [$section_code]
        );
        if (count($section)) return $section[0]->sectionDescription;
        return '';
    }

    protected function getTemplateFilePath(?string $file = null): string
    {
        return base_path('vendor') . '/ltd_ast/violators-report/resources/export_template/' . 'violators_report.xlsx';
    }

    public static function getExportDirName()
    {
        // TODO: Implement getExportDirName() method.
    }

    /**
     * @throws Exception
     */
    function build(string $formatDocument)
    {
        $this->get_plazas();
        $data = $this->get_data();

        $this->prepareDoc();
        $this->drawHeader();
        if (count($data)) $this->drawData($data);
        /* TODO: добавляет подпись директора внизу документа*/
        //$this->drawFooter();

        $name = 'Отчет_по_нарушителям_за_'.$this->date_year;
        return $this->run($this->spreadSheet, $name, $formatDocument);
    }

    private function get_data(): array
    {
        $plazas = "{".implode(",",$this->helper->get_plazas_code($this->plazas))."}";
        return DB::select(
        /** @lang PostgresSQL */'select * from reports."getViolPaymentReport"(?::integer, ?::smallint[]) order by id asc;',
            [
                $this->date_year,
                $plazas,
            ]
        );
        /**
         * id|plaza|fieldDescription               |janCounts|febCounts|marCounts|aprCounts|mayCounts|junCounts|julCounts|augCounts|sepCounts|octCounts|novCounts|decCounts|
        --+-----+-------------------------------+---------+---------+---------+---------+---------+---------+---------+---------+---------+---------+---------+---------+
        1|  101|Интенсивность/ Traffic n/d     |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |
        2|  101|Кол-во наруш./ Viol q-ty n/d   |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |
        3|  101|Стоимость наруш./ Viol rur. n/d|0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |
        4|  101|Кол-во в долг./ IOU q-ty n/d   |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |
        5|  101|Стоимость в долг/ IOU rur. n/d |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |0        |
         */
    }

    public function get_plazas()
    {
        $data = DB::select(
        /** @lang PostgresSQL */'select a."plazaCode", a."stationShortDescription" as "plazaName",b.society as "sectionCode", b."sectionDescription" as "sectionName" from
                    lvl2pdb.stations a
                    left join lvl2pdb.sections b on b.society=a.society
                    where a.enabled=1 and a."isLocal"=1 and a.role=1 and b."sectionDescription"=? and a.id in
                    (select a.id from lvl2pdb.stations a, (select society,"plazaCode",min(id)  as mid from lvl2pdb.stations  group by society,"plazaCode") b
                    where b.society=a.society and b."plazaCode"=a."plazaCode" and a.id=b.mid ) order by "plazaCode"::smallint asc;',
            [$this->section]
        );
        /**
         * example answer
         * |plazaCode|plazaName|sectionCode|sectionName     |
        |---------|---------|-----------|----------------|
        |501      |ПВП-15   |5          |Обход Хабаровска|
        |502      |ПВП-17   |5          |Обход Хабаровска|
        |503      |ПВП-22   |5          |Обход Хабаровска|
        |504      |ПВП-34   |5          |Обход Хабаровска|
        |505      |ПВП-37   |5          |Обход Хабаровска|
         */
        foreach ($data as $el) {
            $this->plazas[$el->plazaCode] = $el->plazaName;
        }
    }

    private function getOperator(): string
    {
        /** @var Employees $employer * */
        $employer = $this->helper->getEmployer();

        return $employer->getFio(true) . '/' . substr($employer->pan, -6);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function prepareDoc()
    {
        $sheet = $this->spreadSheet;
        $sheet->getProperties()->setDescription("Отчёт");
        $sheet->getDefaultStyle()->getFont()->setName('Arial');
        $sheet->getDefaultStyle()->getFont()->setSize(10);
    }

    private function drawHeader()
    {
        $sheet = $this->spreadSheet->getActiveSheet();
        $sheet->setCellValue("B4", $this->section);
        if (count($this->plazas)) {
            $sheet->setCellValue("B5", implode(", ", $this->helper->get_plazas_names($this->plazas)));
        }
        $sheet->setCellValue("B6", $this->date_year . "год");
        $sheet->setCellValue("B7", Carbon::now()->translatedFormat('d.m.Y'));
        $sheet->setCellValue("B8", $this->getOperator());
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function drawData(&$data)
    {
        $sheet = $this->spreadSheet->getActiveSheet();
        foreach ($data as $el) {
            if (str_contains($el->fieldDescription,'Интенсивность/ Traffic')) {
                $this->drawRowHeader();
            }
            //$text = str_replace('n/d','', $el->fieldDescription);
            //$tmp_row = array_merge([$text . $this->plazas[$el->plaza]], $this->get_row($el));
            $tmp_row = array_merge([$el->fieldDescription], $this->get_row($el));
            $sheet->fromArray($tmp_row,NULL,'A'.$this->row);
            $sheet->getStyle("A".$this->row.":M".$this->row)->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($this->row)->setRowHeight(30);
            $sheet->getStyle("A".$this->row.":M".$this->row)->applyFromArray(self::style_hor_center_ver_center);
            $sheet->getStyle("A".$this->row.":M".$this->row)->applyFromArray(self::style_all_borders);
            $this->row++;
        }
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function drawRowHeader()
    {
        $sheet = $this->spreadSheet->getActiveSheet();
        $this->copyRows(9,$this->row,1,13);
        $this->row++;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function copyRows($srcRow, $dstRow, $height, $width) {
        $sheet = $this->spreadSheet->getActiveSheet();
        for ($row = 0; $row < $height; $row++) {
            for ($col = 0; $col <= $width; $col++) {
                $cell = $sheet->getCellByColumnAndRow($col, $srcRow + $row);
                $style = $sheet->getStyleByColumnAndRow($col, $srcRow + $row);
                $dstCell = Coordinate::stringFromColumnIndex($col) . (string)($dstRow + $row);
                $sheet->setCellValue($dstCell, $cell->getValue());
                $sheet->duplicateStyle($style, $dstCell);
            }

            $h = $sheet->getRowDimension($srcRow + $row)->getRowHeight();
            $sheet->getRowDimension($dstRow + $row)->setRowHeight($h);
        }

        foreach ($sheet->getMergeCells() as $mergeCell) {
            $mc = explode(":", $mergeCell);
            $col_s = preg_replace("/[0-9]*/", "", $mc[0]);
            $col_e = preg_replace("/[0-9]*/", "", $mc[1]);
            $row_s = ((int)preg_replace("/[A-Z]*/", "", $mc[0])) - $srcRow;
            $row_e = ((int)preg_replace("/[A-Z]*/", "", $mc[1])) - $srcRow;

            if (0 <= $row_s && $row_s < $height) {
                $merge = $col_s . (string)($dstRow + $row_s) . ":" . $col_e . (string)($dstRow + $row_e);
                $sheet->mergeCells($merge);
            }
        }
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function drawFooter()
    {
        $sheet = $this->spreadSheet->getActiveSheet();
        $data = DB::select(
        /** @lang PostgresSQL */'select "parName","parValue"
                    from reports."F_reportsConfigurations"
                    where "parName"=? or "parName"=? or "parName"=? ',
            ['operatorCompanyCEODescr','operatorCompanyCEOName','operatorCompanyName']
        );
        $info =[];
        foreach ($data as $el) {
            $info[$el->parName] = $el->parValue;
        }
        $this->row++;
        $sheet->getStyle("B".$this->row.":G".$this->row)->applyFromArray(self::style_hor_center_ver_center);
        $sheet->mergeCells('B'.$this->row.':D'.$this->row);
        $sheet->setCellValue('B'.$this->row, $info['operatorCompanyCEODescr']);
        $sheet->mergeCells('E'.$this->row.':F'.$this->row);
        $sheet->mergeCells('G'.$this->row.':K'.$this->row);
        $sheet->setCellValue('G'.$this->row, $info['operatorCompanyCEOName']);
    }

    private function get_row($data): array
    {
        return [
            $data->janCounts,
            $data->febCounts,
            $data->marCounts,
            $data->aprCounts,
            $data->mayCounts,
            $data->junCounts,
            $data->julCounts,
            $data->augCounts,
            $data->sepCounts,
            $data->octCounts,
            $data->novCounts,
            $data->decCounts,
        ];
    }
}
