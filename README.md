# FlatDB
FlatDB is a simple flat file database designed to persist data using just PHP and flat files.
Perfect solution when no other database is available.

**Features:**
- Lightweight and Secure
- Easy to get started, just include flatdb.php to your app
- No external dependencies
- Built-in caching system for better performance
- Powerful API with chaining methods
- CRUD (create, read, update, delete) operations
- Supports custom indexes
- Supports WHERE(), LIMIT(), OFFSET(), ORDER(), FIND(), COUNT() and other methods
- Supports multiple databases and tables

## Usage

### Method Chaining API
FlatDB implements a method chaining API, which means that you can call one method after another like a chain, for example $object->method1()->method2()->method3()

### Installation
```
require_once('src/flatdb.php');
$db = new FlatDB(__DIR__ . '/data');
```

### Insert data into table
```
// If table 'products' is not exist, it will be created on the fly
// The insert function returns the inserted object, note that it added an 'id' key
// The 'id' key is an integer automatically incremented every time an item is added to the table

$result = $db->table('products')->insert(array('name' => 'Cotton Hoodie', 'price' => 48, 'sizes' => array('S', 'L') ));
echo 'The id of the inserted object is: ' . $result['id']; // echoes "The id of the inserted object is: 1"

$db->table('products')->insert(array('name' => 'Knit Hooded Sweater', 'price' => 32, 'sizes' => array('M', 'L', 'XL') ));
$db->table('products')->insert(array('name' => 'Hooded Jacket', 'price' => 51, 'sizes' => array('S', 'M', 'L') ));
$db->table('products')->insert(array('name' => 'Premium Cotton T-Shirt', 'price' => 23, 'sizes' => array('S', 'L', 'XL') ));
```

### Get all entries from table
```
$data = $db->table('products')->all();
var_dump($data);
```

### Return specific columns only
``` 
$data = $db->table('products')->select(array('name','price'))->all();
var_dump($data);
```

### Update data
```
// Update entry with id=1
$db->table('products')->update(1, array(
	'name' => 'Cotton Hoodie',
	'price' => 48.99,
    'sizes' => array('XS', 'S', 'L')
));
```

### Delete data
```
// Remove entry with id=1
$db->table('products')->remove(1);
```

### Find data
FIND() method stops on first match.
```
// Find entry with id=1
$data = $db->table('products')->find(1);

// Find entries with specific column value, e.g. with price = 51
$data = $db->table('products')->find(51, 'price');
```

### Use WHERE() clause
WHERE() method does NOT stop on first match.
```
// Get entries with name='Hooded Jacket'
$data = $db->table('products')->where(array('name' => 'Hooded Jacket'))->all();

// Get entries with price=23
$data = $db->table('products')->where(array('price' => 23))->all();

// Get entries with 'M' sizes
$data = $db->table('products')->where(array('sizes' => 'M'))->all();

// Get entries with 'L' and 'XL' sizes
$data = $db->table('products')->where(array('sizes' => array('L', 'XL')))->all();

// Get the count of entries with 'L' size
$data = $db->table('products')->where(array('sizes' => 'L'))->count();
```

### LIMIT(), OFFSET(), and ORDER()
**IMPORTANT:** To use ORDER() and FIND() methods on specific fields you should add these fields to indexes() first.
```
// Show only latest 5 entries
$data = $db->table('products')->limit(5)->all();

// Skip first 5 entries
$data = $db->table('products')->offset(5)->all();

// Skip first 5 entries and show only 5 entries
$data = $db->table('products')->offset(5)->limit(5)->all();

// Change the order of entries
// By default, the order is ascending (from the smallest to the biggest) by 'id' (oldest entries are the first to be returned)
$data = $db->table('products')->order('desc')->all();

// If you have an index on a column, you apply ORDER() function to it (see Indexes section)
// Order by price (from higher to lower)
$data = $db->table('products')->order('desc', 'price')->all();

// Use ORDER(), OFFSET() and LIMIT() methods together
$data = $db->table('products')->order('desc')->offset(5)->limit(5)->all();
```

### Indexes
By default, the 'id' field is used as an index, however you can define your own custom indexes.
**IMPORTANT:** The 'id' index cannot be removed or modified.

It's recommended to set the indexes early in the lifecycle of your app, before entries are added to the tables, however it's not required, and you call the INDEXES() method anywhere you want.

Indexes are used by ORDER() and FIND() methods.
```
// We can define custom indexes for our table, for example, let's add price 'field' to indexes
$db->table('products')->indexes(array('id', 'price'));

// Now we can use 'price' index within the ORDER() and FIND() methods

// Find entry with price=23
$data = $db->table('products')->find(23, 'price');

// Order entrues by price
$data = $db->table('products')->order('desc', 'price');
```

## Security
FlatDB uses PHP files to store and protect the data, so it cannot be accessed from web-browser by URL.

As an extra layer of security, FlatDB creates an empty index.php for each database folder and table folder to prevent directory listing.  

If you store sensitive information such as passwords consider hashing them, this is a generic tip, not only for FlatDB but for any other database. 
**IMPORTANT:** sha1() or md5() are not safe functions for password hashing, use [password_hash()](http://php.net/manual/en/function.password-hash.php) instead.

## Data Folder Structure (Example)
```
|- data/
|-- index.php
|-- default/ (database name)
     |- index.php
     |- table_1/ (table name)
         |- index.php 
         |- meta.php 
         |- entry_1.php 
         |- entry_2.php 
         |- entry_3.php 
         |- entry_4.php
         |- etc..
		 
     |- table_2/ (table name)
         |- index.php 
         |- meta.php 
         |- entry_1.php 
         |- entry_2.php 
         |- entry_3.php 
         |- etc..
		 
|-- database_2/ (database name)
     |- index.php
     |- table_1/ (table name)
         |- index.php 
         |- etc..
```

## API Reference
| Method                                             | Description                                                            |
|----------------------------------------------------|------------------------------------------------------------------------|
| new FlatDB($data_path, $db_name = 'default')       | Creates a new database instance                                        |
| table($name)                                       | Select a table to work with                                            |
| indexes($array)                                    | Adds passed fields to table indexes       |
| insert($data)                                      | Insert new entry to the current table                                  |
| update($id, $data)                                 | Update entry data with the given id in the current table               |
| remove($id)                                        | Remove entry with the given id from the current table                  |
| order($order = 'asc', [$field = 'id'])             | Change the sort order of returned entries                              |
| limit($number)                                     | Used to constrain number of returned entries                           |
| offset($number)                                    | The number of entries to be skipped                                    |
| skip($ammount)                                     | Alias for offset()                                                     |
| select($array)                                     | Specify returned fields of entries                                     |
| where($array)                                      | Indicates the condition that entries must satisfy to be selected       |
| all()                                              | Return all entries of the query result                                 |
| first()                                            | Return only first entry of the query result                            |
| count()                                            | Return the number of returned entries                                  |
| find($value, [$field = 'id'])                      | Find and return an entry with the given value in specified field       |


## Changelog
```
v1.0.0 - May 12, 2016
** Initial release **
```

## [MIT License](https://opensource.org/licenses/MIT)
(c) 2016 [Max Kostinevich](https://maxkostinevich.com) - All rights reserved.
