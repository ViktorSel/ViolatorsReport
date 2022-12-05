<?php
namespace Ast\ViolatorsReport\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

/**
 *
 */
abstract class Export
{
    const formatXlsx = 'xlsx';
    const formatPdf = 'pdf';

    const typeSaveFile = 'save_file';
    const typeBlob = 'blob';

    const extXlsx = 'xlsx';
    const extPdf = 'pdf';

    const writerTypeXlsx = 'Xlsx';
    const writerTypePdf = 'Mpdf';

    const mimeTypeXlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    const mimeTypePdf = 'application/pdf';

    const startBuildEvent = 'start_build_event';
    const executingBuildEvent = 'executing_build_event';
    const endBuildEvent = 'end_build_event';

    protected $typeResponse = self::typeSaveFile;
    protected $events = [];

    abstract public static function getExportDirName();

    abstract function build(string $formatDocument);

    public function setTypeResponseBlob()
    {
        $this->typeResponse = self::typeBlob;

        return $this;
    }

    public function setTypeResponseSaveFile()
    {
        $this->typeResponse = self::typeSaveFile;

        return $this;
    }

    public function addStartBuildEvent(callable $fn)
    {
        $this->addEvent(self::startBuildEvent, $fn);

        return $this;
    }

    public function addExecutingBuildEvent(callable $fn)
    {
        $this->addEvent(self::executingBuildEvent, $fn);

        return $this;
    }

    public function addEndBuildEvent(callable $fn)
    {
        $this->addEvent(self::endBuildEvent, $fn);

        return $this;
    }

    protected function addEvent(string $eventName, callable $fn)
    {
        $this->events[$eventName] = $fn;
    }

    protected function triggerEvent(string $eventName, array $args = [])
    {
        if (isset($this->events[$eventName])) {
            call_user_func_array($this->events[$eventName], $args);
        }
    }

    /**
     * @param $filePath
     * @return Spreadsheet
     */
    protected function open($filePath)
    {
        return IOFactory::load($filePath);
    }

    /**
     * @param Spreadsheet $spreadSheet
     * @param string $name
     * @param string $formatDocument
     * @return array|bool
     * @throws \Exception
     */
    protected function run(Spreadsheet $spreadSheet, string $name, string $formatDocument)
    {
        try {
            // узнаем формат
            if ($formatDocument === self::formatPdf) {
                $spreadSheet->getActiveSheet()->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $params = $this->getParamsPdf();
            } elseif ($formatDocument === self::formatXlsx) {
                $params = $this->getParamsXlsx();
            } else {
                throw new \Exception('Неверный формат файла');
            }

            $writer = IOFactory::createWriter($spreadSheet, $params['writer_type']);

            // узнаем как сохранять
            if ($this->typeResponse === self::typeBlob) {
                $result = $this->getBlob($writer, $name, $params);
            } elseif ($this->typeResponse === self::typeSaveFile) {
                $result = $this->saveFile($writer, $name, $params);
            } else {
                throw new \Exception('Неверный формат отдачи данных');
            }
        } catch (\Exception $e) {
            $this->resetBuffer($spreadSheet);

            throw $e;
        }

        $this->resetBuffer($spreadSheet);

        return $result;
    }

    protected function drawCell(Worksheet $worksheet, string $cell, $value, string $type = DataType::TYPE_STRING, $wrap = true)
    {
        $worksheet->setCellValueExplicit($cell, $value, $type);

        if ($wrap) {
            $worksheet
                ->getStyle($cell)
                ->getAlignment()
                ->setWrapText(true);
        }
    }

    protected function getTemplateFilePath()
    {
        return resource_path(config('app.export_template_path')) . '/' . 'empty.xlsx';
    }

    public static function getResultDirPath(string $exportDirname)
    {
        return storage_path(config('app.export_result_path')) . '/' . $exportDirname;
    }

    private function getBlob(IWriter $writer, string $name, array $params)
    {
        ob_start();
        $writer->save('php://output');
        $data = ob_get_contents();
        ob_end_clean();

        return [
            // TODO сделать name_file
            'name' => $name . $params['ext'],
            'blob' => 'data:' . $params['mime_type'] . ';base64,' . base64_encode($data),
        ];
    }

    /**
     * @param IWriter $writer
     * @param string $name
     * @param array $params
     * @return bool
     * @throws \Exception
     */
    private function saveFile(IWriter $writer, string $name, array $params)
    {
        $resultDirPath = self::getResultDirPath($this->getExportDirName());

        if (!file_exists($resultDirPath)) {
            $makeDir = @mkdir($resultDirPath, 0777, true);
            if (!$makeDir) {
                throw new \Exception('Возникла ошибка при создании папки по пути: ' . $resultDirPath);
            }
        }

        $writer->save($resultDirPath . '/' . $name . $params['ext']);

        return $name . $params['ext'];
    }

    private function paramsMap()
    {
        return [
            'ext',
            'writer_type',
            'mime_type',
        ];
    }

    private function mergeMap(array $data)
    {
        $result = [];

        foreach ($data as $key => $item) {
            $result[$this->paramsMap()[$key]] = $item;
        }

        return $result;
    }

    private function getParamsXlsx()
    {
        $data = [
            '.' . self::extXlsx,
            self::writerTypeXlsx,
            self::mimeTypeXlsx,
        ];

        return $this->mergeMap($data);
    }

    private function getParamsPdf()
    {
        $data = [
            '.' . self::extPdf,
            self::writerTypePdf,
            self::mimeTypePdf,
        ];

        return $this->mergeMap($data);
    }

    private function resetBuffer(Spreadsheet $worksheet)
    {
        $worksheet->disconnectWorksheets();
        unset($worksheet);
    }
}
