<?php

/*
-- SQL definition
CREATE TABLE `metadata` (
	`id` bigint(20) NOT NULL AUTO_INCREMENT,
	`object_name` varchar(255) DEFAULT NULL,
	`object_id` varchar(255) DEFAULT NULL,
	`name` varchar(255) DEFAULT NULL,
	`value` longtext,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
*/

if (!function_exists('get_metadata'))
{
	function get_metadata($name, $default = NULL, $object_name = FALSE, $object_id = FALSE)
	{
		$ci = &get_instance();

		$where = metadata_where($name, $object_name, $object_id);
		$row = $ci->db->where($where)->get('metadata')->row();

		if (!empty($row) && isset($row->value))
		{
			return $row->value;
		}
		return $default;
	}
}

if (!function_exists('set_metadata'))
{
	function set_metadata($name, $value = NULL, $object_name = FALSE, $object_id = FALSE)
	{
		$ci = &get_instance();

		$where = metadata_where($name, $object_name, $object_id);
		$row = $ci->db->where($where)->get('metadata')->row();

		$ci->db->set(array(
			'name' => $name,
			'value' => $value,
			'object_name' => $object_name,
			'object_id' => $object_id,
		));

		if (!empty($row))
		{
			$ci->db->where('id', $row->id);
			return $ci->db->update('metadata');
		}
		else
		{
			return $ci->db->insert('metadata');
		}
	}
}

if (!function_exists('unset_metadata'))
{
	function unset_metadata($name, $object_name = FALSE, $object_id = FALSE)
	{
		$ci = &get_instance();

		$count = 0;
		$where = metadata_where($name, $object_name, $object_id);
		$rows = $ci->db->where($where)->get('metadata')->result();

		foreach($rows as $row)
		{
			$ci->db->where('id', $row->id);
			$count += $ci->db->delete('metadata') ? 1 : 0;
		}
		return $count;
	}
}

if (!function_exists('metadata_where'))
{
	function metadata_where($name = FALSE, $object_name = FALSE, $object_id = FALSE)
	{
		$where = array();
		if ($name !== FALSE)
		{
			$where['name'] = $name;
		}
		if ($object_name !== FALSE)
		{
			$where['object_name'] = $object_name;
		}
		if ($object_id !== FALSE)
		{
			$where['object_id'] = $object_id;
		}
		return $where;
	}
}
