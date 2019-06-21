<?php

namespace HXC\Admin;

use think\db\Query;

trait Common
{
    /**
     * 列表查询sql捕获
     * @param $sql
     * @return mixed
     */
    public function indexQuery(Query $sql)
    {
        return $sql;
    }

    /**
     * 分页数据捕获，用于追加数据
     * @param $item
     * @param $key
     * @return mixed
     */
    public function pageEach($item, $key)
    {
        return $item;
    }

    /**
     * 输出到列表视图的数据捕获
     * @param $data
     * @return mixed
     */
    public function indexAssign($data)
    {
        $data['lists'] = [
            'hxc' => []
        ];
        return $data;
    }

    /**
     * 输出到新增视图的数据捕获
     * @param $data
     * @return mixed
     */
    public function addAssign($data)
    {
        $data['lists'] = [
            'hxc' => []
        ];
        return $data;
    }

    /**
     * 新增数据插入数据库前数据捕获（注意：在数据验证之前）
     * @param $data
     * @return mixed
     */
    public function addData($data)
    {
        return $data;
    }

    /**
     * 输出到编辑视图的数据捕获
     * @param $data
     * @return mixed
     */
    public function editAssign($data)
    {
        $data['lists'] = [
            'hxc' => []
        ];
        return $data;
    }

    /**
     * 编辑数据插入数据库前数据捕获（注意：在数据验证之前）
     * @param $data
     * @return mixed
     */
    public function editData($data)
    {
        return $data;
    }

    /**
     * 成功添加数据后的数据捕获
     * @param $id @desc 添加后的id
     * @param $data @desc 接受的参数，包含追加的
     * @return mixed|void
     */
    public function addEnd($id, $data)
    {

    }

    /**
     * 成功编辑数据后的数据捕获
     * @param $id @desc 编辑数据的id
     * @param $data @desc 接受的参数，包含追加的
     * @return mixed|void
     */
    public function editEnd($id, $data)
    {

    }

    /**
     * 成功删除数据后的数据捕获
     * @param $id @desc 要删除数据的id
     * @return mixed|void
     */
    public function deleteEnd($id)
    {

    }
}