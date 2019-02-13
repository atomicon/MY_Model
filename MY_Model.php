<?php
/**
 * MY_Model
 *
 * A datamapper like model for CodeIgniter
 *
 * @author Yvo van Dillen
 * @version 1.0.2
 * @access public
 */

class MY_Model extends CI_Model
{
	// contains all information about the tables
	static $table_data = null;

	// when does the table cache needs to be renewed? time to live in seconds (3600=1 hour)
	static $table_data_cache_ttl = 3600;

	// table name for this model
	public $table = null;

	// primary key for the current model
	public $primary_key = null;

	// last query
	public $last_query = '';

	// the data stored for this specific model
	public $stored = array();

	// array of columns to be protected
	public $protected = array();

	// the field containing the slug (optional)
	public $slug_key = 'slug';

	// more results stored in here (where the first is always this)
	public $all = array();

	/**
	 * MY_Model::__construct()
	 *
	 * @param integer $id
	 * @return void
	 */
	function __construct($id = null)
	{
		$this->_get_table();
		$this->_get_table_data();
		if (!empty($id))
		{
			$this->where($this->primary_key, $id);
			$this->get();
			if (!$this->exists())
			{
				if ($this->has_field($this->slug_key))
				{
					$this->where($this->slug_key, $id);
					$this->get();
				}
			}
		}
	}

	/**
	 * MY_Model::id()
	 *
	 * Returns the primary key or the slug depending if it exists
	 *
	 * @return primary_key or slug field
	 */
	function id()
	{
		if (!empty($this->stored[$this->slug_key]))
		{
			return $this->stored[$this->slug_key];
		}
		if (!empty($this->stored[$this->primary_key]))
		{
			return $this->stored[$this->primary_key];
		}
		return null;
	}

	/**
	 * MY_Model::exists()
	 *
	 * Checks if the object is correctly loaded
	 *
	 * @return bool
	 */
	function exists()
	{
		return !empty($this->stored[$this->primary_key]);
	}

	/**
	 * MY_Model::get()
	 *
	 * Same like get in the database class except
	 * fills the stored and variable all variable
	 *
	 * @return
	 */
	function get()
	{
		$result           = $this->db->get($this->table)->result();
		$this->last_query = $this->db->last_query();
		if (empty($result))
		{
			$this->reset();
		}
		else
		{
			$this->all = array();
			foreach ($result as $index => $row)
			{
				if ($index == 0)
				{
					$object =& $this;
				}
				else
				{
					$class  = $this->_get_called_class();
					$object = new $class;
				}
				foreach ($row as $name => $value)
				{
					if (isset($object->stored[$name]))
					{
						$object->stored[$name] = $value;
					}
					else
					{
						$object->$name = $value;
					}
				}
				$this->all[] = $object;
			}
		}
		return $this;
	}

	/**
	 * MY_Model::save()
	 *
	 * Saves the current model to the database
	 * It updates or inserts the data depending
	 * on whether the data exists
	 *
	 * @return bool
	 */
	public function save()
	{
		$result = false;
		$data   = $this->stored;

		if (!in_array($this->primary_key, $this->protected))
		{
			$this->protected[] = $this->primary_key;
		}

		foreach ($data as $index => $value)
		{
			if (in_array($index, $this->protected))
			{
				unset($data[$index]);
			}
		}

		if (!empty($this->slug_key) && $this->has_field($this->slug_key))
		{
			if (empty($data[$this->slug_key]))
			{
				$this->stored[$this->slug_key] = $data[$this->slug_key] = $this->_slugify();
			}
			else
			{
				unset($data[$this->slug_key]);
			}
		}

		if ($this->exists())
		{
			if (array_key_exists('updated', $data))
			{
				$this->stored['updated'] = $data['updated'] = date('Y-m-d H:i:s');
			}
			$this->where($this->primary_key, $this->stored[$this->primary_key]);
			$result = $this->db->update($this->table, $data);
		}
		else
		{
			if (array_key_exists('created', $data))
			{
				$this->stored['created'] = $data['created'] = date('Y-m-d H:i:s');
			}
			if (array_key_exists('updated', $data))
			{
				$this->stored['updated'] = $data['updated'] = date('Y-m-d H:i:s');
			}
			$result = $this->db->insert($this->table, $data);
			if ($result)
			{
				$this->stored[$this->primary_key] = $this->db->insert_id();
			}
		}
		$this->last_query = $this->db->last_query();
		return $result;
	}

	/**
	 * MY_Model::delete()
	 *
	 * deletes the current data from the database
	 *
	 * @return
	 */
	public function delete()
	{
		$result = false;
		if ($this->exists())
		{
			// delete self
			$this->where($this->primary_key, $this->stored[$this->primary_key]);
			$result = $this->db->delete($this->table);
		}
		$this->reset();
		return $result;
	}

	/**
	 * MY_Model::fill()
	 *
	 * @param object|array $data
	 * @param boolean $only_fill_stored if true it will only fill the stored variable
	 * @return self
	 */
	public function fill($data, $only_fill_stored = TRUE)
	{
		if (is_array($data) || is_object($data))
		{
			foreach ($data as $name => $value)
			{
				if ($only_fill_stored)
				{
					if (array_key_exists($name, $this->stored))
					{
						$this->$name = $value;
					}
				}
				else
				{
					$this->$name = $value;
				}
			}
		}
		return $this;
	}

	/**
	 * MY_Model::reset()
	 *
	 * Empties the current model and fills the data for the model
	 *
	 * @return self
	 */
	public function reset()
	{
		$this->all = array();
		if (isset(MY_Model::$table_data[$this->table]))
		{
			foreach (MY_Model::$table_data[$this->table] as $field)
			{
				if (empty($this->primary_key) && $field['primary_key'] == 1)
				{
					$this->primary_key = $field['name'];
				}
				$this->stored[$field['name']] = $field['default'];
			}
		}
		return $this;
	}

	/**
	 * MY_Model::count_all()
	 *
	 * Counts all records with the current parameters and options
	 * It will clone the codeigniter database and query on the clone
	 *
	 * @return int
	 */
	public function count_all()
	{
		$db = clone $this->db;
		$row = $db->select('COUNT(*) as count_all')->get($this->table)->row();
		if (!empty($row->count_all))
		{
			return $row->count_all;
		}
		return 0;
	}

	/**
	 * MY_Model::as_options()
	 *
	 * Creates an associative array for the use of dropdowns
	 *
	 * @param string $key The key (column) to use as the key
	 * @param string $value The value (column) to use as the label
	 * @param bool $get_if_empty Retrieve the dataset if it is empty
	 * @return array
	 */
	public function as_options($key = 'id', $value = 'title', $get_if_empty = true)
	{
		$options = array();
		if (!$this->exists() && $get_if_empty)
		{
			$this->get();
		}
		foreach ($this->all as $model)
		{
			$options[$model->$key] = $model->$value;
		}
		return $options;
	}

	/**
	 * MY_Model::has_field()
	 *
	 * @param string $field The fieldname to check
	 * @param string $table Optional table to check on
	 * @return boolean
	 */
	public function has_field($field, $table = null)
	{
		$table = empty($table) ? $this->table : $table;
		if (!empty(MY_Model::$table_data[$table]))
		{
			foreach (MY_Model::$table_data[$table] as $data)
			{
				if ($data['name'] == $field)
				{
					return TRUE;
				}
			}
		}
		return FALSE;
	}

	/**
	 * MY_Model::delete_cache()
	 *
	 * Removes the table_data cache file
	 * @return boolean
	 */
	static function delete_cache()
	{
		$cache_file = APPPATH . 'cache/table_data.php';
		if (is_file($cache_file))
		{
			return @unlink($cache_file);
		}
		return true;
	}

	/********************************
	 * Magic functions
	 *******************************/
	/**
	 * MY_Model::__get()
	 *
	 * First checks if $name exists in the $stored variable
	 * Second checks for the function __get_$name()
	 * Third checks to see if it exists in the model
	 * Fourth checks if the codeigniter has it
	 *
	 * @param mixed $name Name of the property or value to get
	 * @return mixed
	 */
	public function __get($name)
	{
		if (array_key_exists($name, $this->stored))
		{
			return $this->stored[$name];
		}
		else if (method_exists($this, '__get_' . $name))
		{
			return call_user_func(array(
				$this,
				'__get_' . $name
			));
		}
		else if (isset($this->$name))
		{
			return $this->$name;
		}
		else if (isset(get_instance()->$name))
		{
			return get_instance()->$name;
		}
		return null;
	}

	/**
	 * MY_Model::__set()
	 *
	 * @param string $name Name of the property to set
	 * @param mixed $value The value of the property
	 * @return void
	 */
	public function __set($name, $value)
	{
		if (array_key_exists($name, $this->stored))
		{
			$this->stored[$name] = $value;
		}
		else
		{
			$this->$name = $value;
		}
	}

	/**
	 * MY_Model::__isset()
	 *
	 * @param string $name Name of the property to test
	 * @return bool
	 */
	public function __isset($name)
	{
		if (array_key_exists($name, $this->stored))
		{
			return true;
		}
		else
		{
			return isset($this->$name);
		}
	}

	/**
	 * MY_Model::__call()
	 *
	 * First checks if this model has a function by the name
	 * Seconds tries the database if that function exists
	 *
	 * @param string $name name of the function to call
	 * @param mixed $arguments Array of arguments
	 * @return
	 */
	public function __call($name, $arguments)
	{
		if (method_exists($this, $name))
		{
			return call_user_func_array(array(
				$this,
				$name
			), $arguments);
		}
		else if (method_exists($this->db, $name))
		{
			call_user_func_array(array(
				$this->db,
				$name
			), $arguments);
			return $this;
		}
		else
		{
			$trace = debug_backtrace();
			trigger_error('Undefined method  ' . $name . ' in ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'], E_USER_NOTICE);
			return null;
		}
	}

	/***********************************
	 * Private and protected functions
	 **********************************/

	/**
	 * MY_Model::_get_table_data()
	 *
	 * Lists all tables and gets the definition of all tables
	 * If caching is enabled (MY_Model::$table_data_cache_ttl > 0) it will cache the
	 * table data for that amount of time
	 *
	 * @return void
	 */
	private function _get_table_data()
	{
		if (!is_array(MY_Model::$table_data))
		{
			$cache_file = APPPATH . 'cache/table_data.php';
			if (MY_Model::$table_data_cache_ttl > 0 && file_exists($cache_file))
			{
				if (time() - filemtime($cache_file) < MY_Model::$table_data_cache_ttl)
				{
					include $cache_file;
					if (!empty($table_data))
					{
						MY_Model::$table_data = $table_data;
						$this->reset();
						return;
					}
				}
			}

			MY_Model::$table_data = array();
			if (isset(get_instance()->db))
			{
				$tables = $this->db->list_tables();
				foreach ($tables as $table)
				{
					MY_Model::$table_data[$table] = $this->db->field_data($table);
					foreach (MY_Model::$table_data[$table] as &$field)
					{
						$field = (array) $field;
					}
				}
			}
			if (MY_Model::$table_data_cache_ttl > 0)
			{
				$data = '<?php' . PHP_EOL . '$table_data = ' . var_export(MY_Model::$table_data, true) . ';';
				file_put_contents($cache_file, $data);
			}
		}
		$this->reset();
	}

	/**
	 * MY_Model::_get_table()
	 *
	 * If $table is not set, this function tries to guess the
	 * pluralized table name based on the model name
	 * e.g. User_model = users
	 *
	 * @return
	 */
	private function _get_table()
	{
		if (!is_string($this->table))
		{
			$this->load->helper('inflector');
			$class       = preg_replace('#((_m|_model)$|$(m_))?#', '', strtolower($this->_get_called_class()));
			$this->table = plural(strtolower($class));
		}
	}

	/**
	 * MY_Model::_slugify()
	 *
	 * Generate a unique slug for the current row based on the table and a UUID
	 *
	 * @return string
	 */

	protected function _slugify($str = '')
	{
		$this->load->helper('inflector');
		while (1)
		{
			$str  = empty($str) ? str_replace(array('a', 'e', 'u', 'i', 'o'), '', singular($this->table)) : $str;
			$slug = empty($str) ? '' : $str . '-';

			$uuid   = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				// 32 bits for "time_low"
				mt_rand(0, 0xffff), mt_rand(0, 0xffff),
				// 16 bits for "time_mid"
				mt_rand(0, 0xffff),
				// 16 bits for "time_hi_and_version",

				// four most significant bits holds version number 4
				mt_rand(0, 0x0fff) | 0x4000,
				// 16 bits, 8 bits for "clk_seq_hi_res",

				// 8 bits for "clk_seq_low",

				// two most significant bits holds zero and one for variant DCE1.1
				mt_rand(0, 0x3fff) | 0x8000,
				// 48 bits for "node"
				mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
			);
			$slug   = url_title("{$slug}{$uuid}", '-', true);
			$result = $this->db->where($this->slug_key, $slug)->limit(1)->get($this->table)->result();
			if (empty($result))
			{
				break;
			}
		}
		return $slug;
	}

	/********************************
	 * Compatibility functions
	 *******************************/

	/**
	 * MY_Model::_get_called_class()
	 *
	 * Get the name of the calling model class
	 *
	 * @return string
	 */
	private function _get_called_class()
	{
		if (function_exists('get_called_class'))
		{
			return get_called_class();
		}
		else
		{
			$objects = array();
			$traces  = debug_backtrace();
			foreach ($traces as $trace)
			{
				if (isset($trace['object']))
				{
					if (is_object($trace['object']))
					{
						$objects[] = $trace['object'];
					}
				}
			}
			if (count($objects))
			{
				return get_class($objects[0]);
			}
		}
	}
}
