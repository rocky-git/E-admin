<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2020-06-15
 * Time: 22:12
 */

namespace Eadmin\grid\excel;


use Eadmin\service\QueueService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use think\facade\Filesystem;

/**
 * Class Excel
 * @package Eadmin\grid\excel
 * @mixin \PhpOffice\PhpSpreadsheet\Spreadsheet
 */
class Excel extends AbstractExporter
{
    protected $excel;

    protected $sheet;

    protected $callback = null;

    protected $mapCallback = null;
    //数据开始列
    protected $startColumnIndex = 1;
    //数据开始行
    protected $startRowIndex = 2;
    protected $rowIndex = 0;
    //合并行字段条件
    protected $mergeCondtionField = null;
    //合并列字段
    protected $mergeRowFields = [];
    protected $fieldCellArr = [];
    protected $init = false;
    public function __construct()
    {
        $this->excel = new Spreadsheet();
        $this->sheet = $this->excel->getActiveSheet();
    }

    public function getLetter($i)
    {

        $letter = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
        if ($i > count($letter) - 1) {
            if ($i > 51) {
                $num = floor($i / 26);
            } else {
                $num = 1;
            }
            $j = $i % 26;
            $str = $letter[$num - 1] . $letter[$j];

            return $str;
        } else {
            return $letter[$i];
        }
    }

    /**
     * @param \Closure $closure
     */
    public function callback(\Closure $closure)
    {
        $this->callback = $closure;
    }

    /**
     * 设置数据遍历开始行列
     * @param int $startRowIndex 行
     * @param int $startColumnIndex 列
     */
    public function setDataIndex($startRowIndex = 2, $startColumnIndex = 1)
    {
        $this->startRowIndex = $startRowIndex;
        $this->startColumnIndex = $startColumnIndex;
    }
    protected function init(){
        if(!$this->init){
            if (is_callable($this->callback)) {
                call_user_func($this->callback, $this);
            }
            set_time_limit(0);
            ini_set('memory_limit', '-1');
            $this->filterColumns();
            $rowCount = count($this->data) + 1;
            $letter = $this->getLetter(count($this->columns) - 1);
            $this->sheet->getStyle("A1:{$letter}{$rowCount}")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
            ]);
            $i = 0;
            foreach ($this->columns as $field => $val) {
                $values = array_column($this->data, $field);
                $str = $val;
                foreach ($values as $v) {
                    if (mb_strlen($v, 'utf-8') > mb_strlen($str, 'utf-8')) {
                        $str = $v;
                    }
                }
                $width = ceil(mb_strlen($str, 'utf-8') * 2);
                $this->sheet->getColumnDimension($this->getLetter($i))->setWidth($width);
                $i++;
            }
            $i = 0;
            foreach ($this->columns as $field => $val) {
                $i++;
                $this->sheet->setCellValueByColumnAndRow($i, 1, $val);
                $this->fieldCellArr[$field] = $this->getLetter($i - 1);
            }
            $this->rowIndex = $this->startRowIndex - 1;
            $this->init = true;
        }
    }
    public function queueExport($count){
        $this->init();
        $this->writeRowData();
        $queue = new QueueService(request()->get('system_queue_id'));
        $queue->percentage($count,$this->rowIndex-1,'正在导出');
        if($this->rowIndex > $count){
            $writer = IOFactory::createWriter($this->excel, 'Xls');
            $path = Filesystem::path('excel');
            $filesystem = new \Symfony\Component\Filesystem\Filesystem;
            $filesystem->mkdir($path);
            $writer->save($path.DIRECTORY_SEPARATOR.$this->fileName . '.xls');
            $queue->progress('/upload/excel/' . $this->fileName . '.xls');
        }
    }
    public function export()
    {
        $this->init();
        $this->writeRowData();
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $this->fileName . '.xls"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($this->excel, 'Xls');
        $writer->save('php://output');
        exit;
    }

    private static function filterEmoji($str)
    {
        $str = preg_replace_callback('/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);
        return $str;
    }

    public function map(\Closure $closure)
    {
        $this->mapCallback = $closure;
    }

    /**
     * 合并行
     * @param string $conditionField 条件字段(判断重复合并行)
     * @param array $fields 合并字段列
     */
    public function mergeRow(string $conditionField, array $fields)
    {
        $this->mergeCondtionField = $conditionField;
        $this->mergeRowFields = $fields;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->excel, $name], $arguments);
    }
    /**
     * 设置数据到excel
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function writeRowData()
    {
        $tmpMergeIndex = $this->rowIndex +1;
        $rowCount = count($this->data) + 1;
        $tmpMergeCondition = '';
        foreach ($this->data as $key => &$val) {
            $this->rowIndex++;
            if ($this->mapCallback instanceof \Closure) {
                $val = call_user_func($this->mapCallback, $val, $this->sheet);
            }
            $startColumnIndex = $this->startColumnIndex;
            foreach ($this->columns as $fkey => $fval) {
                $this->sheet->setCellValueByColumnAndRow($startColumnIndex, $this->rowIndex, $this->filterEmoji($val[$fkey]));
                $startColumnIndex++;
            }
            if (!is_null($this->mergeCondtionField)) {
                if ($tmpMergeCondition != $val[$this->mergeCondtionField] || $this->rowIndex == $rowCount) {
                    if (!empty($tmpMergeCondition)) {
                        foreach ($this->mergeRowFields as $field) {
                            $letter = $this->fieldCellArr[$field];
                            if ( $this->rowIndex == $rowCount) {
                                if ($tmpMergeCondition != $val[$this->mergeCondtionField]) {
                                    break;
                                }
                                $mergeIndex =  $this->rowIndex;
                            } else {
                                $mergeIndex =  $this->rowIndex - 1;
                            }
                            $this->sheet->mergeCells("{$letter}{$tmpMergeIndex}:{$letter}{$mergeIndex}");
                        }
                    }
                    $tmpMergeCondition = $val[$this->mergeCondtionField];
                    $tmpMergeIndex =  $this->rowIndex;
                }
            }
        }
    }
}
