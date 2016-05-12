<?php
/**
 * FlatDB class
 *
 * Super-simple flat files database
 *
 * http://github.com/maxkostinevich/flatdb/
 * https://maxkostinevich.com
 *
 * @author Max Kostinevich <hello@maxkostinevich.com>
 * @copyright 2016 Max Kostinevich
 * @license MIT License
 */

/**
 * FlatDB is a simple flat file database, designed to persist data using just PHP and flat files.
 * Perfect solution when no other database available.
 *
 * Examples:
 * $db = new FlatDB(__DIR__ . '/data');
 * $db->table('products')->insert(array('name' => 'Hoodie')); // return added entry object { id: 1, name: 'Hoodie' }
 * $db->table('products')->find(1); // return an entry with id=1
 * $db->table('products')->remove(1); // delete entry with id=1
 * $db->table('products')->all(); // return all entries
 */
class FlatDB
{
    /**
     * The path where database files will be stored
     */
    private $data_dir;

    /**
     * Internal query
     */
    private $query;

    /**
     * Internal indexes
     */
    private $indexes;

    /**
     * Cache for table metadata
     */
    private $meta_cache;

    /**
     * @var $version
     */
    public static $version = '1.0.0';

    /**
     * Creates a new database instance
     *
     * @param string $data_path The path where the database files will
     *  be stored, can be any path as long as write permissions are granted.
     *
     * @param string $db The current database in use, defaults to 'default' for
     *  production database, you can use this if you need to several databases
     */
    public function __construct($data_path, $db = 'default') {

        $this->data_dir = $data_path . '/' . $db . '/';
        $this->indexes = array();
        $this->meta_cache = array();
		
		// Create data directory
        if(!is_dir($this->data_dir)) {
            if(!mkdir($this->data_dir)) {
                throw new Exception('Could not create database table, permission denied.');
            }else{
				// Add empty index.php file if directory has been created successfully
				file_put_contents($this->data_dir . 'index.php', '<?php //Silence is golden');
			}
        }
    }

    /**
     * Selects a table to work on
     *
     * Selects a table to work on, this is a chainable method, and must be
     * called on every query.
     *
     * @param string $name The name of the table to work with, if not created
     *  yet, it will be created when inserting data.
     *
     * @return object The current class instance, for chainability
     */
    public function table($name) {
        $this->query = new FlatDB_Query($name);

        // chainability
        return $this;
    }
    
    /**
     * Inserts an entry to a table
     *
     * Inserts an entry to a table, a table must be selected by now using the
     *  _table_ method.
     *
     * @param array $obj The associative array to be inserted
     *
     * @return array The inserted object, with an 'id' key, holding the unique
     *  id of this entry in the table.
     */
    public function insert($obj) {
        if(!is_array($obj)) {
            throw new Exception('Can only write arrays');
        }

        if($this->query->was_run()) {
            throw new Exception('Query already ran');
        }

        $table = $this->query->table;
        $id = 0;
        $meta = null;

        // Find the id of the new entry
        if(!is_dir($this->data_dir . $table)) {
            // this is the first entry, create directory
            if(!@mkdir($this->data_dir . $table, 0777)) {
                throw new Exception('Could not create table folder, permission denied.');
            }else{
				// Add empty index.php file if directory has been created successfully
				file_put_contents($this->data_dir . $table . '/' . 'index.php', '<?php //Silence is golden');
			}

            // id starts from 1
            $id = 1;

            // create an empty metadata array
            $meta = array(
                'last_id' => 0,
                'count' => 0,
                'indexes' => array(),
            );
        } else {
            // this is not the first entry, read table metadata, and calculate
            // new id
            $meta = $this->meta();
            $id = $meta['last_id'] + 1;
        }

        // add the id key to the object to be inserted
        $obj['id'] = $id;

        // create new file
        $this->write($table . '/entry_' . $id . '.php', $obj);

        // Save new metadata
        $meta['last_id'] = $id;

        // check for indexes
        if(array_key_exists($table, $this->indexes)) {
            // if custom indexes are defined, add them to the meta
            foreach($this->indexes[$table] as $index) {
                if(!$obj[$index]) {
                    throw new Exception("Table $table has an index on $index, but trying to insert an array without that field.");
                }

                $meta['indexes'][$index][] = $obj[$index];
            }
        } else {
            // if there are no custom indexes, just add the id to the meta
            $meta['indexes']['id'][] = $id;
        }

        $meta['count'] = $meta['count'] + 1;
        $this->write($table . '/meta.php', $meta);
        $this->meta_cache[$table] = $meta;

        // invalidate cache
        $this->invalidate_cache($table);

        // mark as executed
        $this->query->run();

        // Return the new entry data
        return $obj;
    }

    /**
     * Update an entry from a table
     *
     * Updates an entry from a table, which must be selected using the _table_
     * method.
     *
     * @param int $id The id of the entry to update
     *
     * @param array $val The new values of the entry, it's important to note
     *  that you must specify _all_ values, as this array actually replaces the
     *  old one
     *
     * @return array The inserted array, with the appropiate 'id' value
     */
    public function update($id, $val) {
        if($this->query->was_run()) {
            throw new Exception('Query already ran');
        }

        $table = $this->query->table;
        $entry_file = $this->data_dir . $table . '/entry_' . $id . '.php';
        if(!file_exists($entry_file)) {
            throw new Exception('Could not find entry with id ' . $id);
        }

        // check if an index is modified
        $meta = $this->meta();
        $old_entry = $this->read($entry_file, false);
        $update_indexes = false;
        $indexes = array_keys($meta['indexes']);
        foreach($indexes as $index) {
            if($index == 'id') {
                continue;
            }

            if($old_entry[$index] != $val[$index]) {
                $update_indexes = true;
                break;
            }
        }

        // if an index update is needed
        if($update_indexes) {
            $key = array_search($old_entry['id'], $meta['indexes']['id']);
            foreach($indexes as $index) {
                if($index == 'id') {
                    continue;
                }

                $meta['indexes'][$index][$key] = $val[$index];
            }

            $this->write($table . '/meta.php', $meta);
            $this->meta_cache[$table] = $meta;
        }

        // id cannot be changed
        $val['id'] = $old_entry['id'];

        // persist updated entry
        $this->write($entry_file, $val, false);

        // invalidate cache
        $this->invalidate_cache($table);

        // mark as executed
        $this->query->run();

        // return persisted entry
        return $val;
    }

    /**
     * Removes an entry from a table
     *
     * @param mixed $id The id or array of ids of the entries to be removed
     *
     * @return object An instance of this class, for chainability
     */
    public function remove($id) {
        if($this->query->was_run()) {
            throw new Exception('Query already ran');
        }

        $table = $this->query->table;

        // if we are removing multiple entries
        if(is_array($id)) {
            foreach($id as $index) {
                $this->table($table)->remove($index);
            }

            return $this;
        }

        if(!file_exists($this->data_dir . $table . '/entry_' . $id . '.php')) {
            throw new Exception('Could not find entry with id: ' . $id);
        }

        $meta = $this->meta();
        // remove indexes
        $key = array_search($id, $meta['indexes']['id']);
        foreach(array_keys($meta['indexes']) as $index) {
            unset($meta['indexes'][$index][$key]);
        }
        // update counter
        $meta['count'] = $meta['count'] - 1;
        // remove entry file
        unlink($this->data_dir . $table . '/entry_' . $id . '.php');
        // save new metadata
        $this->write($table . '/meta.php', $meta);
        $this->meta_cache[$table] = $meta;

        // invalidate cache
        $this->invalidate_cache($table);

        // mark as executed
        $this->query->run();

        // chainability
        return $this;
    }

    /**
     * Invalidates the cache for a given table
     *
     * @param string $table The table which cache will be invalidated
     */
    private function invalidate_cache($table) {
        foreach(glob($this->data_dir . $table . '/cache_*') as $file) {
            unlink($file);
        }
    }

    /**
     * Finds an entry in a table by id
     *
     * @param int $val The id of the entry to look for
     * @param string $field The field which must meet the value, by default it's "id"
     *
     * @return array The entry with the specified id
     */
    public function find($val, $field = 'id') {
        // If we are finding by id just read the entry
        if($field == 'id') {
            $this->query->id = $val;
            return $this->findById();
        }

        // If we are finding by index, use the table matadata
        $meta = $this->meta();

        if(!array_key_exists($field, $meta['indexes'])) {
            throw new Exception("The field $field is not a table index");
        }

        $array_idx = array_search($val, $meta['indexes'][$field]);

        // if the entry doesn't exist, return null
        if(false === $array_idx) {
            return null;
        }

        // success! we got the id
        $id = $meta['indexes']['id'][$array_idx];
        return $this->find($id);
    }

    /**
     * Sets the order of the next query to be run
     *
     * @param string $ord Can either be _desc_ or _asc_, for descending and
     *  ascending order respectively, by default, it's _desc_
     * 
     * @param string $key The key used to sort entries, by default it's _id_
     *
     * @return object The current class instance, for chainability
     */
    public function order($ord = 'DESC', $key = 'id') {
        $this->query->order = array('key' => $key, 'mode' => strtoupper($ord));

        // chainability
        return $this;
    }

    /**
     * The limits of results for the next query to be run
     *
     * @param int $limit The maximum results the next query will return
     *
     * @return object The current class instance, for chainability
     */
    public function limit($limit) {
        $this->query->limit = $limit;

        // chainability
        return $this;
    }

    /**
     * Select the fields the next query will return
     *
     * When entries have many fields, it might be convenient to just return the
     * ones desired.
     *
     * @param array $keys An array of the entry keys to be returned by the next
     * query
     *
     * @return object The current class instance, for chainability
     */
    public function select($keys) {
        $this->query->select = $keys;

        // chainability
        return $this;
    }

    /**
     * Alias of the offset function
     *
     * @param int $offset How many entries to skip
     * @return object The current class instance, for chainability
     */
    public function skip($offset) {
        return $this->offset($offset);
    }

    /**
     * Sets the offset of the next query
     *
     * Basically it skips as many results of the next query as this function
     * specifies
     *
     * @param int $offset How many entries to skip
     *
     * @return object The current class instance, for chainability
     */
    public function offset($offset) {
        $this->query->offset = $offset;

        // chainability
        return $this;
    }

    /**
     * Filter all returned entries to match certain values
     *
     * @param mixed $arr An array with keys/values the entries must satisfy
     *
     * @return object The current class instance, for chainability
     */
    public function where($arr = null) {

        $this->query->where = $arr;
        return $this;
    }

    /**
     * Runs the query, and returns all elements
     *
     * @returns All the elements the query returned
     */
    public function all() {
        return $this->findAll();
    }

    /**
     * Runs the query and returns only the first element
     *
     * @returns The first element of the query result, or null if query returned no elements
     */
    public function first() {
        $all = $this->all();

        if(count($all) == 0) {
            return null;
        }

        return current($all);
    }

    /**
     * Counts the entries of a table
     *
     * @return The ammount of entries a table holds
     */
    public function count() {
        $limit = $this->query->limit;
        $offset = $this->query->offset;
        $where = $this->query->where;

        if(!is_null($limit) || !is_null($offset) || !is_null($where)) {
            return count($this->all());
        }

        $meta = $this->meta();
        return $meta['count'];
    }

    /**
     * Gets the metadata information for a table
     *
     * Normally not needed unless you are working with FlatDB in a very low 
     * level.
     *
     * @return array The metadata of the selected table
     */
    public function meta() {
        $table = $this->query->table;

        // if the meta was not yet loaded, load it!
        if(!array_key_exists($table, $this->meta_cache)) {
            $path = $this->data_dir . $table . '/meta.php';
            if(!file_exists($path)) {
                throw new Exception("Metadata for table $table not found");
            }

            $this->meta_cache[$table] = $this->read($path, false);
        }

        return $this->meta_cache[$table];
    }

    /**
     * Set indexes of a table
     *
     * Indexes are needed to use the 'order' method on fields other than 'id'
     *
     * @param array $arr An array of desired fields to be indexes of the table
     *
     * @return int A status code, if bigger than 0, the method was successful
     */
    public function indexes($arr) {
        $table = $this->query->table;

        if(!$table) {
            throw new Exception('Table not specified, cannot define indexes');
        }

        if(!is_array($arr)) {
            throw new Exception('Invalid indexes definition, must be an array');
        }

        if(!in_array('id', $arr)) {
            $arr[] = 'id';
        }

        $this->indexes[$table] = $arr;

        // mark query as ran
        $this->query->run();

        // hopefully the table was not used yet
        $meta = null;
        try {
            // try to reset the metadata for all entries of the table
            $meta = $this->meta();

            // if the indexes are correct, just ignore
            if(array_keys($meta['indexes']) === $arr) {
                return 2;
            }

            // if they changed...
            foreach($arr as $index) {
                $meta['indexes'][$index] = array();

                foreach($this->table($table)->all() as $entry) {
                    $meta['indexes'][$index][] = $entry[$index];
                }
            }

            $this->write($table . '/meta.php', $meta);
            $this->meta_cache[$table] = $meta;
        } catch (Exception $e) {
            // table does not exist, no need to modify metadata
        }

        return 1;
    }

    /**
     * Private helper, finds an entry by id
     */
    private function findById() {
        if($this->query->was_run()) {
            throw new Exception('Query already ran');
        }

        $table = $this->query->table;
        $select = $this->query->select;
        $id = $this->query->id;
        $path = $this->data_dir . $table . '/entry_' . $id . '.php';

        // mark query as executed
        $this->query->run();

        if(file_exists($path)) {
            $entry = $this->read($path, false);
            return is_null($select) ? $entry : $this->selectFields($select, $entry);
        }

        return null;
    }

    /**
     * Helper function to filter fields in an entry
     *
     * @param array $select The fields desired in the output
     * @param array $entry The entry to be filtered
     */
    private function selectFields($select, $entry) {
        $new_entry = array();
        foreach($select as $key) {
            if(array_key_exists($key, $entry)) {
                $new_entry[$key] = $entry[$key];
            }
        }

        return $new_entry;
    }

    /**
     * Helper function to find all the results of a query
     */
    private function findAll() {
        if($this->query->was_run()) {
            throw new Exception('Query already ran');
        }

        $table = $this->query->table;
        $order = $this->query->order;
        $limit = $this->query->limit;
        $offset = $this->query->offset;
        $where = $this->query->where;
        $select = $this->query->select;

        // seek for cache
        $cache_name = sha1($table . serialize($order) . $limit . $offset . serialize($where) . serialize($select));
        $cache_file = $this->data_dir . $table . '/cache_' . $cache_name . '.php';

        if(file_exists($cache_file)) {
            // if there's a cache, just mark the query as ran and return
            $this->query->run();
            return $this->read($cache_file, false);
        }

        // cache was not found, keep on
        // get table metadata
        $metadata = $this->meta();

        // order
        // get indexed key
        $key = $order['key'];
        $mode = $order['mode'];
        $index = $metadata['indexes'][$key];

        // If no entries... Just return null already
        if(empty($metadata['indexes']['id'])) {
          return null;
        }

        // Now sort
        $indexes_arr = array_combine($index, $metadata['indexes']['id']);
        if($mode === 'DESC') {
            krsort($indexes_arr);
        } else {
            ksort($indexes_arr);
        }

        // limit and offset
        if($limit > 0) {
            $indexes_arr = array_slice($indexes_arr, $offset, $limit);
        } else if($offset > 0) {
            $indexes_arr = array_slice($indexes_arr, $offset);
        }

        $output = array();
        $entry = null;

        if(is_null($where)) {
            foreach($indexes_arr as $idx => $id) {
                $entry = $this->read($table . '/entry_' . $id . '.php');
                // check for select
                $output[] = is_null($select) ? $entry : $this->selectFields($select, $entry);
            }
        } else {
            // We've got a filter! Compare all entries
            foreach($indexes_arr as $idx => $id) {
                $entry = $this->read($table . '/entry_' . $id . '.php');
                $add = true;

                // check for closure
                if(is_callable($where)) {
					throw new Exception('Closure is not allowed in WHERE clause');
                } else {
                  // For each entry, see if it satisfies the filters
                  foreach($where as $key => $value) {
                      if(array_key_exists($key, $entry) && is_array($entry[$key])) {
                          // if the entry value is an array, use in_array
                          if(is_array($value)) {
                              // if the needle is also an array
                              foreach($value as $item) {
                                  if(!in_array($item, $entry[$key])) {
                                      $add = false;
                                      break;
                                  }
                              }
                          } else {
                              // if not just seek for a needle in the array
                              if(!in_array($value, $entry[$key])) {
                                  $add = false;
                                  break;
                              }
                          }
                      } else {
                          // if not, just compare using ==
                          if($entry[$key] != $value) {
                              $add = false;
                              break;
                          }
                      }
                  }
                }

                if($add) {
                    $output[] = is_null($select) ? $entry : $this->selectFields($select, $entry);
                }
            }
        }

        // mark query as executed
        $this->query->run();

        // create cache
        $this->write($cache_file, $output, false);
        return $output;
    }

    /**
     * Writes an object to a file
     *
     * @param string $path The path where the file is located
     * @param array $obj The associative array to be saved onto the file
     * @param boolean $relative Whether the path argument is a relative path or not
     */
    private function write($path, $obj, $relative = true) {
        if($relative) {
            $path = $this->data_dir . $path;
        }

        file_put_contents($path, '<?php exit(); ?>' . serialize($obj), LOCK_EX);
    }

    /**
    * Reads an object from a file
    *
    * @param string $path The path where the file is located
    * @param boolean $relative Whether the path argument is a relative path or not
    *
    * @returns array An associative array with the data stored in the file
    */
    private function read($path, $relative = true) {
        if($relative) {
            $path = $this->data_dir . $path;
        }

        $contents = file_get_contents($path);
        return unserialize(substr($contents, 16));
    }
}

/**
 * Helper class to keep an internal query state
 */
class FlatDB_Query
{
    /**
     * The table this query works on
     */
    public $table = null;

    /**
     * The order the entries will be returned
     */
    public $order;

    /**
     * The limit of entries this query must return
     */
    public $limit = 0;

    /**
     * The offset this query must skip
     */
    public $offset = 0;

    /**
     * The id of the entry
     */
    public $id = 0;

    /**
     * Entries must satisfy this filter 
     */
    public $where = null;

    /**
     * The fields to be selected
     */
    public $select = null;

    /**
     * Every time a method which returns data is called, the query must be set up all over again.
     */
    private $executed = false;

    /**
     * Query constructor
     *
     * @param string $name The name of the table this query works on
     */
    public function __construct($name) {
        $this->table = $name;
        $this->order = array('key' => 'id', 'mode' => 'ASC');
    }

    /**
     * Mark this query as executed
     */
    public function run() {
        $this->executed = true;
    }

    /**
     * Whether this query was run or not
     */
    public function was_run() {
        return $this->executed;
    }
}