<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-03-06
 * Time: 17:31
 */

namespace HXC\Admin;

use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Request;
use think\response\Json;
use think\Session;
use think\Validate;

/**
 * Trait curd
 * @package app\admin\library\hxc
 * @property $countField
 */
trait curd
{
    /**
     * 列表页
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function index(Request $request)
    {
        if (!$request->param('page')) {
            Session::clear($this->modelName);
        }
        $special = [];
        $only_arr = [];
        $condition = [];
        $where = [];
        foreach ($this->searchField as $k => $v) {
            if (is_array($v)) {
                $key = key($v);
                $val = $v[$key];
                $only_arr[] = $key;
                if (is_array($val)) {
                    if ($val[1] == 'select') {
                        $condition[$key] = '';
                    }
                }
                $special[$key] = $val;
            } else {
                $only_arr[] = $v;
            }
        }
        if ($request->isPost()) {
            $this->search($request, $request->only($only_arr), $special);
        }
        $condition['pageSize'] = Session::get('pageSize', $this->modelName) ?: $this->pageLimit;
        $whereData = Session::get('', $this->modelName);
        foreach ($whereData as $k => $v) {
            if ($k != 'pageSize' && $k != 'RelationSearch') {
                switch ($v['type']) {
                    case 'select':
                        $where[$v['field'] ?: $k] = $v['val'];
                        $condition[$k] = $v['val'];
                        break;
                    case 'time_start':
                        $where[$v['field'] ?: $k][] = ['>=', $v['val'] . ' 00:00:00'];
                        $condition[$k] = $v['val'];
                        break;
                    case 'time_end':
                        $where[$v['field'] ?: $k][] = ['<=', $v['val'] . ' 23:59:59'];
                        $condition[$k] = $v['val'];
                        break;
                    default:
                        if ($v['condition'] == 1) {
                            $where[$v['field'] ?: $k] = $v['val'];
                        } else {
                            $where[$v['field'] ?: $k] = ['like', "%{$v['val']}%"];
                        }
                        $condition[$k] = $v['val'];
                        $condition["{$k}Condition"] = $v['condition'];
                        break;
                }
            }
        }
        $pageSize = Session::get('pageSize', $this->modelName) ?: $this->pageLimit;
        $list = $this->indexQuery($this->getSql($where))
            ->order($this->orderField)
            ->paginate($pageSize)
            ->each(function ($item, $key) {
                return $this->pageEach($item, $key);
            });
        $countFiled = [];
        if (property_exists($this, "countField")) {
            $countFun = function ($arr) use (&$countFiled, $where) {
                if ($arr) {
                    foreach ($arr as $v) {
                        $countFiled[$v] = $this->indexQuery($this->getSql($where))->sum($v);
                    }
                }
            };
            $countFun($this->countField);
        }
        $pagelist = $list->render();
        $data = [
            'list' => $list,
            'pagelist' => $pagelist,
            'countField' => $countFiled
        ];
        if ($condition) {
            $data['params'] = $condition;
        }
        $this->assign($this->indexAssign($data));
        return $this->fetch();
    }

    /**
     * 条件查询
     * @param $request
     * @param $params
     * @param $special
     */
    public function search(Request $request, $params, $special)
    {
        $page = $request->post('pageSize');
        if ($page) {
            Session::set('pageSize', $page, $this->modelName);
        }
        $condition = [];
        foreach ($params as $k => $v) {
            if ($v) {
                $data = isset($special[$k]) ? $special[$k] : $k;
                $type = '';
                if (is_array($data)) {
                    $field = $data[0];
                    $type = $data[1];
                    if ($type == 'relation' && strpos($field, '.') !== false) {
                        $name = explode('.', $field, 2);
                        $name[0] = strtolower($name[0]);
                        Session::set('RelationSearch', $name[0], $this->modelName);
                    }
                } else {
                    $field = $k;
                }
                $condition[$k] = $v;
                $condition["{$k}Condition"] = '';
                if ($request->post("{$k}Condition") == 1) {//精确查询
                    Session::set($k, [
                        'val' => $v,
                        'condition' => 1,
                        'field' => $field,
                        'type' => $type
                    ], $this->modelName);
                } else {//模糊查询
                    Session::set($k, [
                        'val' => $v,
                        'condition' => 0,
                        'field' => $field,
                        'type' => $type
                    ], $this->modelName);
                }
            }
        }
    }

    /**
     * 列表和条件查询sql
     * @param array $where
     * @return \think\Model
     */
    protected function getSql($where = [])
    {
        foreach ($where as $k=>$v) {
            if (!isset($v[1])) {
                $where[$k] = $v[0];
            }
        }
        $field = implode(',', $this->indexField);
        $relationSearch = Session::get('RelationSearch', $this->modelName);
        if (!empty($relationSearch)) {
            $model = model($this->modelName)->$relationSearch()->hasWhere([], null);
        } else {
            $model = model($this->modelName);
        }
        if($this->cache){
            $model->cache(true, 0, $this->modelName . 'cache_data');
        }
        return $model->field($field)->where($where);
    }

    /**
     * 新增数据页
     * @param Request $request
     * @return Json
     */
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $params = $request->only($this->addField);
            $add_data = $this->addData($params);
            if (is_object($add_data)) {
                return $add_data;
            }
            $validate = new Validate($this->add_rule);
            $result = $validate->check($add_data);
            if (!$result) {//验证不通过
                return json_err(-1, $validate->getError());
            } else {//验证通过
                if ($this->addTransaction) {
                    Db::startTrans();
                }
                $model = model($this->modelName);
                $res = $model->allowField(true)->save($add_data);
                if ($res) {
                    $addId = $model->getPk();
                    $addEndRes = $this->addEnd($addId, $add_data);
                    if (is_object($addEndRes)) {
                        if ($this->addTransaction) {
                            Db::rollback();
                        }
                        return $addEndRes;
                    }
                    if ($this->addTransaction) {
                        Db::commit();
                    }
                    return json_suc();
                } else {
                    if ($this->addTransaction) {
                        Db::rollback();
                    }
                    return json_err();
                }
            }
        } else {
            $this->assign($this->addAssign([]));
            return $this->fetch();
        }
    }

    /**
     * 编辑数据页
     * @param Request $request
     * @return Json
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function edit(Request $request)
    {
        $id = $request->param('id');
        if (!$id) {
            return json_err(-1, '参数有误，缺少id');
        }
        if ($request->isPost()) {
            $params = $request->only($this->editField);
            $edit_data = $this->editData($params);
            if (is_object($edit_data)) {
                return $edit_data;
            }
            $validate = new Validate($this->edit_rule);
            $result = $validate->check($edit_data);
            if (!$result) {//验证不通过
                return json_err(-1, $validate->getError());
            } else {//验证通过
                if ($this->editTransaction) {
                    Db::startTrans();
                }
                $res = model($this->modelName)->allowField(true)->save($edit_data, ['id' => $id]);
                if ($res) {
                    $editEndRes = $this->editEnd($id, $edit_data);
                    if (is_object($editEndRes)) {
                        if ($this->editTransaction) {
                            Db::rollback();
                        }
                        return $editEndRes;
                    }
                    if ($this->editTransaction) {
                        Db::commit();
                    }
                    return json_suc();
                } else {
                    if ($this->editTransaction) {
                        Db::rollback();
                    }
                    return json_err();
                }
            }
        } else {
            $data = model($this->modelName)->find($id);
            $this->assign($this->editAssign([
                'id' => $id,
                'data' => $data
            ]));
            return $this->fetch();
        }
    }

    /**
     * 删除
     * @param Request $request
     * @return Json
     * @throws Exception
     */
    public function delete(Request $request)
    {
        $id = $request->param('id');
        if ($this->deleteTransaction) {
            Db::startTrans();
        }
        $data = model($this->modelName)->get($id);
        if (empty($data)) {
            return json_err();
        }
        if ($data->delete()) {
            $delEndRes = $this->deleteEnd($id);
            if (is_object($delEndRes)) {
                if ($this->deleteTransaction) {
                    Db::rollback();
                }
                return $delEndRes;
            }
            if ($this->deleteTransaction) {
                Db::commit();
            }
            return json_suc();
        } else {
            if ($this->deleteTransaction) {
                Db::rollback();
            }
            return json_err();
        }
    }
}