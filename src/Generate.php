<?php

namespace HXC;

use think\Config;
use think\Db;
use think\Loader;
use think\Request;

trait Generate
{
    public function __construct()
    {
        if (!file_exists(ROOT_PATH . '/hxc.lock')) {
            echo '无权操作';
            die;
        }
    }

    public function index()
    {
        return view();
    }

    /**
     * 展示所有的表
     */
    public function showTables()
    {
        $database = Config::get('database.database');
        $prefix = Config::get('database.prefix');
        $data = Db::query('show tables');
        $res = [];
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $res[$k]['value'] = str_replace($prefix, '', $v['Tables_in_' . $database]);
                $res[$k]['label'] = str_replace($prefix, '', $v['Tables_in_' . $database]);
            }
        }
        return $this->res($res, '没有数据表，请添加数据表后刷新重试');
    }

    /**
     * 统一返回
     * @param $data
     * @param string $errorTips
     * @return false|string
     */
    protected function res($data, $errorTips = '')
    {
        if (!$data || empty($data)) {
            $res = [
                'code' => 0,
                'msg' => $errorTips ?: '空数据'
            ];
        } else {
            $res = [
                'code' => 1,
                'data' => $data
            ];
        }
        return json_encode($res);
    }

    /**
     * 获取对应数据表的字段数据
     * @param Request $request
     * @return false|string|void
     */
    public function getTableFieldData(Request $request)
    {
        if ($request->isPost()) {
            $table = $request->param('table');
            $is_model = $request->param('isModel');
            if ($is_model) {
                $model = model("app\app\model\\$table");
                $table = $model->getTable();
            }
            $prefix = Config::get('database.prefix');
            $res = [];
            $data = Db::query("SHOW FULL COLUMNS FROM `{$prefix}{$table}`");
            if (!empty($data)) {
                foreach ($data as $k => $v) {
                    $res[$k]['name'] = $v['Field']; //字段名
                    $res[$k]['comment'] = $v['Comment']; //注释
                    $res[$k]['type'] = $v['Type']; //类型
                    $res[$k]['label'] = ''; //名称
                    $res[$k]['curd'] = []; //操作
                    $res[$k]['business'] = ''; //业务类型
                    $res[$k]['search'] = false; //检索
                    $res[$k]['require'] = $v['Null'] == 'NO';//必填
                    $res[$k]['length'] = preg_replace('/\D/s', '', $v['Type']);//字段长度，不严谨
                }
            }
            return $this->res($res, '数据表中未定义字段，请添加后刷新重试');
        }
    }

    /**
     * 获取模型
     * @return false|string
     */
    public function getModelData()
    {
        $model_path = ROOT_PATH . 'application\app\model\*.php';
        $res = [];
        foreach (glob($model_path) as $k => $v) {
            $val = explode('.php', explode('\model\\', $v)[1])[0];
            $arr = [
                'value' => $val,
                'label' => $val,
                'children' => [],
                'loading' => false
            ];
            $res[] = $arr;
        }
        return $this->res($res, '没有控制器');
    }

    /**
     * 生成
     * @param Request $request
     * @return false|string|void
     */
    public function generate(Request $request)
    {
        if ($request->isPost()) {
            $tpl = include(ROOT_PATH . 'tpl.php');
            $data = json_decode($request->post('data'), true);
            $table_name = $request->post('tableName');
            if (!$table_name || !$data || !$data['selectVal']) {
                return json_err(0, '参数错误');
            }

            if ($data['selectVal'] == '后台') {
                $dir = 'admin';
            } elseif ($data['selectVal'] == '前台') {
                $dir = 'app';
            } else {
                return json_err(0, '参数错误');
            }
            $controller_name = $request->post('controllerName');
            if (!$controller_name) {
                $controller_name = $table_name;
            }
            $controller_name = Loader::parseName($controller_name, 1);
            $model_name = Loader::parseName($table_name, 1);

            list($indexField, $index_field, $editField, $edit_field, $addField, $add_field, $searchField, $search_field, $autoType) = [[], [], [], [], [], [], [], [], []];
            $addRule = '';
            $editRule = '';
            $orderField = '';
            foreach ($data['pageData'] as $k => $v) {
                if (in_array('查', $v['curd'])) {
                    $indexField[] = "'{$v['name']}'";
                    $index_field[$v['name']] = $v['label'];
                }
                if (in_array('改', $v['curd'])) {
                    $editField[] = "'{$v['name']}'";
                    $edit_field[$v['name']] = [
                        'label' => $v['label'],
                        'tpl' => $tpl['table'][$v['business']],
                        'attr' => $v['require'] ? 'data-rule="required;"' : ''
                    ];
                    if ($v['require']) {
                        $editRule .= "        '{$v['name']}|{$v['label']}' => 'require',\n";
                    }
                }
                if (in_array('增', $v['curd'])) {
                    $addField[] = "'{$v['name']}'";
                    $add_field[$v['name']] = [
                        'label' => $v['label'],
                        'tpl' => $tpl['table'][$v['business']],
                        'attr' => $v['require'] ? 'data-rule="required;"' : ''
                    ];
                    if ($v['require']) {
                        $addRule .= "        '{$v['name']}|{$v['label']}' => 'require',\n";
                    }
                }
                if ($v['search'] == true) {
                    $searchField[] = "'{$v['name']}'";
                    $search_field[$v['name']] = $v['label'];
                }
                if (!empty($v['autotype'])) {
                    $autoType[$v['name']] = $v['autotype'];
                }
                if (!empty($v['sort'])) {
                    $orderField .= "{$v['name']} {$v['sort']},";
                }
            }
            $orderField = rtrim($orderField, ',');
            $indexField = implode(',', $indexField);
            $editField = implode(',', $editField);
            $addField = implode(',', $addField);
            $searchField = implode(',', $searchField);
            foreach ($data['fruit'] as $k => $v) {
                if ($v == '控制器') {
                    $controller_path = APP_PATH . "{$dir}/controller/{$controller_name}.php";
                    if (file_exists($controller_path)) {
                        return json_encode(['code' => 0, 'msg' => '控制器已存在']);
                    }
                    $controller_code = <<<CODE
    protected \$modelName  = '{$model_name}';  //模型名,用于add和update方法
    protected \$indexField = [{$indexField}];  //查，字段名
    protected \$addField   = [{$addField}];    //增，字段名
    protected \$editField  = [{$editField}];   //改，字段名
    /**
     * 条件查询，字段名,例如：无关联查询['name','age']，关联查询['name','age',['productId' => 'product.name']],解释：参数名为productId,关联表字段p.name
     * 默认的类型为输入框，如果有下拉框，时间选择等需求可以这样定义['name',['type' => ['type','select']]];目前有select,time_start,time_end三种可被定义
     * 通过模型定义的关联查询，可以这样定义['name',['memberId'=>['member.nickname','relation']]],只能有一个关联方法被定义为relation，且字段前的表别名必须为关联的方法名
     * @var array
     */
    protected \$cache = false; //是否开启缓存查询，仅对后台查询生效，通过模型方式进行增，改，删的操作，都会刷新缓存
    protected \$searchField = [{$searchField}];
    protected \$orderField = '$orderField';  //排序字段
    protected \$pageLimit   = 10;               //分页数
    protected \$addTransaction = false;        //添加事务是否开启，开启事务证明你需要在addEnd方法里追加业务逻辑
    protected \$editTransaction = false;       //编辑事务是否开启，开启事务证明你需要在editEnd方法里追加业务逻辑
    protected \$deleteTransaction = false;     //删除事务是否开启，开启事务证明你需要在deleteEnd方法里追加业务逻辑
    
    //增，数据检测规则
    protected \$add_rule = [
        //'nickName|昵称'  => 'require|max:25'
{$addRule}
    ];
    //改，数据检测规则
    protected \$edit_rule = [
        //'nickName|昵称'  => 'require|max:25'
{$editRule}
    ];
CODE;
                    $controller_code = $this->getControllerCode($controller_name, $controller_code, $data, $model_name, $orderField);
                    file_put_contents($controller_path, $controller_code);
                }
                if ($v == '模型') {
                    $model_path = APP_PATH . "{$dir}/model/{$model_name}.php";
                    if (file_exists($model_path)) {
                        return json_encode(['code' => 0, 'msg' => '模型已存在']);
                    }
                    $model_code = $this->getModelCode($model_name, $data, $autoType);
                    file_put_contents($model_path, $model_code);
                }
                if ($dir == 'admin' && $v == '视图') {
                    $view_dir_name = Loader::parseName($controller_name);
                    $view_dir = APP_PATH . "{$dir}/view/{$view_dir_name}/";
                    if (is_dir($view_dir)) {
                        return json_encode(['code' => 0, 'msg' => '视图目录已存在']);
                    } else {
                        mkdir($view_dir);
                        file_put_contents($view_dir . 'index.html', $this->getIndexViewCode($index_field, $search_field));
                        file_put_contents($view_dir . 'add.html', $this->getAddViewCode($add_field));
                        file_put_contents($view_dir . 'edit.html', $this->getEditViewCode($edit_field));
                    }
                }
                if ($dir == 'app') {//创建验证文件
                    $validate_path = APP_PATH . "{$dir}/validate/{$controller_name}.php";
                    $validate_code = $this->getValidateCode($controller_name);
                    file_put_contents($validate_path, $validate_code);
                }
            }
            return json_encode(['code' => 1]);
        }
    }

    /**
     * 获取控制器代码
     * @param $controller_name
     * @param $code
     * @param $data
     * @return string
     */
    protected function getControllerCode($controller_name, $code, $data, $model_name, $order)
    {
        if ($data['selectVal'] == '后台') {
            $namespace = 'namespace app\admin\controller;';
            $extends = 'extends Right implements curdInterface';
            $use = <<<USE
use HXC\Admin\Common;
use HXC\Admin\curd;
use HXC\Admin\curdInterface;
USE;
            $html = <<<HTML
    /**
     * 特别说明
     * Common中的文件不能直接修改！！！！
     * 如果需要进行业务追加操作，请复制Common中的对应的钩子方法到此控制器中后在进行操作
     * Happy Coding
     **/
    use curd, Common;

{$code}
HTML;
        } else {//前台
            $namespace = 'namespace app\app\controller;';
            if ($data['login'] == '否') {
                $extends = 'extends Controller';
            } else {
                $extends = 'extends SignInController';
            }

            $html = $this->getAppControllerCode($controller_name, $model_name, $order);
            $use = <<<USE
use think\Controller;
use think\Request;
use HXC\App\Common;
use HXC\App\Curd;
USE;
        }
        return <<<CODE
<?php
{$namespace}

{$use}

class {$controller_name} {$extends}
{
{$html}
}
CODE;
    }

    /**
     * 获取前台的控制器代码
     * @return string
     */
    protected function getAppControllerCode($controller_name, $model_name, $order)
    {
        return <<<HTML
    /**
    * 增删改查封装在Curd内，如需修改复制到控制器即可
    */
    use Common,Curd;
    
    protected \$model = '{$model_name}';
    
    protected \$validate = '{$controller_name}'; 
    
    protected \$with = '';//关联关系
    
    protected \$cache = false;//是否开启缓存查询，仅对前台查询生效，通过模型方式进行增，改，删的操作，都会刷新缓存
    
    protected \$order = '{$order}';

HTML;
    }

    /**
     * 获取模型代码
     * @param $model_name
     * @param $data
     * @param $autoType
     * @return string
     */
    public function getModelCode($model_name, $data, $autoType)
    {
        $mainCode = '';
        $use = "use think\Model;\n";
        $time_status = 'false';
        if ($data['selectVal'] == '前台') {
            $namespace = 'namespace app\app\model;';
            if ($data['delete'] === '是') {
                $use .= "use traits\model\SoftDelete;\n";
                $mainCode = 'use SoftDelete;';
            }
            $time_status = ($data['time'] == '是' ? 'true' : 'false');
        } else {
            $namespace = 'namespace app\admin\model;';
            if (in_array('开启软删', $data['model'])) {
                $use .= "use traits\model\SoftDelete;\n";
                $mainCode .= "use SoftDelete;\n";
            }

            if (in_array('自动时间戳', $data['model'])) {
                $time_status = 'true';
            }
        }

        if (!empty($autoType)) {
            $mainCode .= "protected \$type = [\n";
            foreach ($autoType as $name => $type) {
                $mainCode .= "        '$name' => '$type',\n";
            }
            $mainCode .= "    ];\n";
        }

        return <<<CODE
<?php
{$namespace}

use think\Cache;
{$use}

class {$model_name} extends Model
{
    /**
     * 初始化
     */
    protected static function init()
    {
        \$event_arr = ['afterWrite', 'afterDelete'];
        \$model_name = self::getModel()->name;
        foreach (\$event_arr as \$k => \$v) {
            self::{\$v}(function (\$model) use (\$model_name) {
                Cache::clear(\$model_name . 'cache_data');
            });
        }
    }
    
    {$mainCode}
    // 自动维护时间戳
    protected \$autoWriteTimestamp = {$time_status};
    // 定义时间戳字段名
    protected \$createTime = 'create_time';
    protected \$updateTime = 'update_time';
}
CODE;
    }

    /**
     * 获取列表视图代码
     * @param $index_field
     * @param $search_field
     * @return string
     */
    public function getIndexViewCode($index_field, $search_field)
    {
        $html1 = '';
        $html2 = '';
        $html3 = '';
        foreach ($index_field as $k => $v) {
            $html1 .= "            <th nowrap=\"nowrap\">{$v}</th>\n";
            $html2 .= "                <td nowrap=\"nowrap\">{\$vo.{$k}}</td>\n";
        }
        foreach ($search_field as $k => $v) {
            $html3 .= "        {include file=\"tpl/search\" results=\"params\" name=\"{$k}\" label=\"{$v}\" attr=''/}\n";
        }
        return <<<CODE
{include file="tpl/style"/}
<form role="form" id="searchForm" action="{:url('index')}" method='post' class="form-horizontal">
    <div class="form-group">
{$html3}
        <div class="col-xs-12">
            <div class="col-xs-4 col-sm-3 col-md-2 col-lg-1 pull-left">
                <div class="row">
                    {include file='tpl/addBtn' url="add" height="80%" width="30%"/}
                </div>
            </div>
            <div class="col-xs-4 col-sm-3 col-md-2 col-lg-1">
                <div class="row">
                    <span></span>
                </div>
            </div>
            <div class="col-xs-4 col-sm-3 col-md-2 col-lg-1 pull-right">
                <div class="row">
                    {include file="tpl/searchBtn" /}
                    {include file="tpl/reloadBtn" /}
                </div>
            </div>
        </div>
    </div>
</form>
<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
        <tr>
{$html1}
            <th nowrap="nowrap">操作</th>
        </tr>
        </thead>
        <tbody>
        {volist name="list" id="vo"}
            <tr>
{$html2}
                <td nowrap="nowrap">
                    <!--编辑资料-->
                    <i class="fa fa-edit qg-op-btn qg-tooltip" data-toggle="tooltip" data-placement="top" title="编辑" onclick="modal('{:url(\'edit\',[\'id\'=>\$vo[\'id\']])}', '编辑','80%','50%')"></i>
                    <!--删除-->
                    <i class="fa fa-trash-o qg-tooltip qg-op-btn" data-toggle="tooltip" data-placement="top" title="删除" onclick="confirmUpdate('{:url(\'delete\')}','{\$vo.id}','确定要删除吗？')"></i>
                </td>
            </tr>
        {/volist}
        </tbody>
    </table>
    <div style="float: right;">{\$pagelist}</div>
</div>
CODE;
    }

    /**
     * 获取添加视图代码
     * @param $add_field
     * @return string
     */
    public function getAddViewCode($add_field)
    {
        $html = '';
        foreach ($add_field as $k => $v) {
            $html .= sprintf($v['tpl'], $k, $v['label'], '', $v['attr']) . "\n";
        }
        return <<<CODE
{include file="common/fileinput"/}
{include file="common/ueditor"/}
{include file="tpl/style"/}
<div class="col-xs-12">
    <div class="row">
        <form class="form-horizontal" role="form" id="form" action="{:url('add')}">
            <div class="form-group">
                {$html}
            </div>
            <div class="form-group" style="margin-top: 20px;">
                {include file="tpl/button" label="保存"/}
            </div>
        </form>
    </div>
</div>
CODE;
    }

    /**
     * 获取编辑视图代码
     * @param $edit_field
     * @return string
     */
    public function getEditViewCode($edit_field)
    {
        $html = '';
        foreach ($edit_field as $k => $v) {
            $html .= sprintf($v['tpl'], $k, $v['label'], $k, $v['attr']) . "\n";
        }
        return <<<CODE
{include file="common/fileinput"/}
{include file="common/ueditor"/}
{include file="tpl/style"/}
<div class="col-xs-12">
    <div class="row">
        <form class="form-horizontal" role="form" id="form" action="{:url('edit')}">
            <div class="form-group">
                <input type="hidden" name="id" value="{\$data.id}">
                {$html}
            </div>
            <div class="form-group" style="margin-top: 20px;">
                {include file="tpl/button" label="保存"/}
            </div>
        </form>
    </div>
</div>
CODE;
    }

    /**
     * 生成验证文件
     * @param $controller_name
     * @return string
     */
    public function getValidateCode($controller_name)
    {
        return <<<CODE
<?php
namespace app\app\\validate;

use think\Validate;

class {$controller_name} extends Validate
{
    protected \$rule = [
        'id' => 'require'
    ];

    protected \$message = [
        'id.require'  =>  'id不能为空',
    ];

    protected \$scene = [
        'delete' => ['id'],//删
        'update' => ['id'],//改
        'store' => [''],//增
        'index' => [''],//查
    ];
}
CODE;
    }

    /**
     * 生成关联关系
     */
    public function generateRelation(Request $request)
    {
        $params = $request->param();
        $model_name = $params['tableName'];
        $data = json_decode($params['data'], true);
        $class_name = "app\app\model\\{$model_name}";
        $model = new $class_name;
        $path = APP_PATH . "app/model/{$model_name}.php";
        $html = rtrim(file_get_contents($path), '}');
        foreach ($data['pageData'] as $k => $v) {
            if (is_array($v['business']) && !empty($v['business'])) {
                $fun = ($v['table'][0]);
                $fun_name = empty($v['fun_name']) ? strtolower($fun) : $v['fun_name'];
                $exists = method_exists($model, $fun_name);
                if (!$exists) {
                    switch ($v['business'][0]) {
                        case '1v1':
                            $has = "hasOne({$fun}::class,'{$v['table'][1]}','{$v['name']}');";
                            break;
                        case '1vm':
                            $has = "hasMany({$fun}::class,'{$v['table'][1]}','{$v['name']}');";
                            break;
                        case 'mvm':
                            $has = "belongsToMany({$fun}::class,'{$v['business'][1]}');";
                            break;
                    }
                    $html .= <<<CODE
                    
    public function {$fun_name}()
    {
        return \$this->{$has}
    }

CODE;
                }
            }
        }

        $html .= <<<CODE
               
}
CODE;
        file_put_contents($path, $html);
        return json_encode(['code' => 1]);
    }
}
