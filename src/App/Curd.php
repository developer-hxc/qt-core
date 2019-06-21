<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-05-14
 * Time: 9:04
 */

namespace HXC\App;

use think\Cache;
use think\exception\DbException;
use think\Request;
use think\response\Json;

trait Curd
{
    /**
     * 每页显示的数量
     * @var int
     */
    protected $limit = 10;

    /**
     * @param Request $request
     * @return Json|void
     * @throws DbException
     */
    public function index(Request $request)
    {
        /**
         * 遵循RESTful API
         * get 查
         * post 增
         * put 改
         * delete 删
         */
        switch ($request->method()) {
            case 'GET':
                return $this->get($request);
                break;
            case 'POST':
                return $this->post($request);
                break;
            case 'PUT':
                return $this->put($request);
                break;
            case 'DELETE':
                return $this->delete($request);
                break;
        }
    }

    /**
     * 查
     * @param Request $request
     * @return Json|void
     * @throws DbException
     */
    protected function get($request)
    {
        if ($request->isGet()) {
            $id = $request->param('id');
            $sql = model($this->model)->with($this->with)->order($this->order);
            if ($this->cache) {
                $sql = $sql->cache(true, 0, $this->model . 'cache_data');
            }
            if ($id) {
                $res = $sql->find($id);
                $flag = $res;
            } else {
                $res = $sql->paginate($this->limit);
                $flag = $res->toArray()['data'];
            }
            return $this->returnRes($flag, '数据不存在', $res);
        }
    }

    /**
     * 增
     * @param Request $request
     * @return Json|void
     */
    protected function post(Request $request)
    {
        if ($request->isPost()) {
            $params = $request->param();
            $params_status = $this->validate($params, "{$this->validate}.store");
            if (true !== $params_status) {
                // 验证失败 输出错误信息
                return $this->returnFail($params_status);
            }
            $res = model($this->model)->allowField(true)->save($params);
            return $this->returnRes($res, '创建失败');
        }
    }

    /**
     * 改
     * @param Request $request
     * @return Json|void
     */
    protected function put(Request $request)
    {
        if ($request->isPut()) {
            $params = $request->param();
            $params_status = $this->validate($params, "{$this->validate}.update");
            if (true !== $params_status) {
                // 验证失败 输出错误信息
                return $this->returnFail($params_status);
            }
            $res = model($this->model)->allowField(true)->save($params, ['id' => $params['id']]);
            return $this->returnRes($res, '编辑失败');
        }
    }

    /**
     * 删
     * @param Request $request
     * @return Json|void
     * @throws DbException
     */
    protected function delete(Request $request)
    {
        if ($request->isDelete()) {
            $params = $request->param();
            $params_status = $this->validate($params, "{$this->validate}.delete");
            if (true !== $params_status) {
                // 验证失败 输出错误信息
                return $this->returnFail($params_status);
            }
            $data = model($this->model)->get($params['id']);
            $res = $data->delete();
            return $this->returnRes($res, '删除失败');
        }
    }
}