<?php


namespace Eadmin\grid;


use Eadmin\component\Component;
use Eadmin\component\grid\Column;
use Eadmin\component\grid\Pagination;
use Eadmin\component\layout\Content;
use think\facade\Request;
use think\helper\Str;
use think\Model;

/**
 * 表格
 * Class Grid
 * @package Eadmin\grid
 * @method $this size(string $size) Radio的尺寸，仅在border为真时有效 medium / small / mini
 * @method $this height(int $height) 高度
 * @method $this maxHeight(int $height) 最大高度
 * @method $this stripe(bool $bool) 是否为斑马纹
 * @method $this border(bool $bool) 是否带有纵向边框
 * @method $this fit(bool $bool) 列的宽度是否自撑开
 * @method $this defaultExpandAll(bool $bool) 是否默认展开所有行
 * @method $this showHeader(bool $bool) 是否显示表头
 * @method $this highlightCurrentRow(bool $bool) 是否要高亮当前行
 * @method $this headerRowStyle(array $value) 表头行样式
 * @method $this rowStyle(array $value) 行样式
 * @method $this cellStyle(array $value) 单元格样式
 * @method $this headerCellStyle(array $value) 表头单元格的 style样式
 * @property Pagination $pagination
 */
class Grid extends Component
{
    use GridModel;
    protected $name = 'EadminGrid';

    protected $column = [];

    protected $pagination;

    //是否隐藏分页
    protected $hidePage = false;

    public function __construct($data)
    {
        if ($data instanceof Model) {
            $this->setModel($data);
        } else {
            $this->data = $data;
        }
        $this->headerCellStyle([
            'background'=>'linear-gradient(to top,#fafafa,#ffffff)',
            'color'=>'#606266',
            'borderTop'=>'solid 1px #ededed'
        ]);
        $this->pagination = new Pagination();
        $this->pagination->pageSize(20)->layout('total, sizes, prev, pager, next, jumper');

    }

    public static function create($data)
    {
        return new static($data);
    }
    /**
     * 关闭分页
     */
    public function hidePage(bool $bool = true)
    {
        $this->hidePage = $bool;
    }
    /**
     * 设置分页每页限制
     * @Author: rocky
     * 2019/11/6 14:01
     * @param $limit
     */
    public function setPageLimit($limit)
    {
        $this->pagination->pageSize($limit);
    }
    /**
     * 添加表格列
     * @param string $field 字段
     * @param string $label 显示的标题
     * @return Column
     */
    public function column(string $field = '', string $label = '')
    {
        $column = new Column($field, $label);
        $this->column[] = $column;
        $this->realiton($field);
        return $column;
    }
    protected function parseColumn(){
        $tableData = [];
        foreach ($this->data as &$row){
            foreach ($this->column as $column) {
               $column->row($row);
            }
        }
        $field = Str::random(15, 3);
        $this->bind($field, $this->data);
        $this->bindAttr('data', $field);
    }
    public function jsonSerialize()
    {
        $this->pagination->total($this->getCount());
        $this->modelData();;
        if(!$this->hidePage){
            $this->data = array_splice($this->data,0,$this->pagination->attr('pageSize'));
            $this->attr('pagination',$this->pagination->attribute);
        }
        $this->parseColumn();
        $this->attr('columns',array_column($this->column,'attribute'));
        return parent::jsonSerialize(); // TODO: Change the autogenerated stub
    }
}
