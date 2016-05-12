<html>
<header>
	<title>FlatDB - Examples</title>
</header>
<body>
<h1>FlatDB - Examples</h1>
<p>FlatDB is a simple flat file database, designed to persist data using just PHP and flat files. <a href="http://github.com/maxkostinevich/flatdb/" target="_blank">Read more</a></p>
<?php

echo '<h2>MAIN DATABASE (default)</h2>';

// Include FlatDB
require_once('flatdb.php');

// Init main database
$db = new FlatDB(__DIR__ . '/data');

// Insert an entry to table 'products'
// If table 'products' is not exist, it will be created on the fly
// The insert function returns the inserted object, note that it added an 'id' key
// The 'id' key is an integer automatically incremented every time an item is added to the table
$result = $db->table('products')->insert(array('name' => 'Cotton Hoodie', 'price' => 48, 'sizes' => array('S', 'L') ));
echo 'The id of the inserted object is: ' . $result['id']; // echoes "The id of the inserted object is: 1"
echo '<hr>';

$db->table('products')->insert(array('name' => 'Knit Hooded Sweater', 'price' => 32, 'sizes' => array('M', 'L', 'XL') ));
$db->table('products')->insert(array('name' => 'Hooded Jacket', 'price' => 51, 'sizes' => array('S', 'M', 'L') ));
$db->table('products')->insert(array('name' => 'Premium Cotton T-Shirt', 'price' => 23, 'sizes' => array('S', 'L', 'XL') ));

// Get all entries from table
$data = $db->table('products')->all();
echo 'PRODUCTS:';
var_dump($data);
echo '<hr>';

// Return specific columns only
$data = $db->table('products')->select(array('name','price'))->all();
echo 'PRODUCTS (name and price only):';
var_dump($data);
echo '<hr>';

// Update entry with id=1
$db->table('products')->update(1, array(
	'name' => 'Cotton Hoodie',
	'price' => 48.99,
    'sizes' => array('XS', 'S', 'L')
));
$data = $db->table('products')->find(1);
echo 'First Product has been updated:';
var_dump($data);
echo '<hr>';

// Remove entry with id=1
$db->table('products')->remove(1);

$data = $db->table('products')->all();
echo 'PRODUCTS (First product has been removed):';
var_dump($data);
echo '<hr>';

// Get entries with name='Hooded Jacket'
$data = $db->table('products')->where(array('name' => 'Hooded Jacket'))->all();
echo 'WHERE(): PRODUCTS (name=Hooded Jacket):';
var_dump($data);
echo '<hr>';

// Get entries with price=23
$data = $db->table('products')->where(array('price' => 23))->all();
echo 'WHERE(): PRODUCTS (price=23):';
var_dump($data);
echo '<hr>';

// Get entries with 'M' sizes
$data = $db->table('products')->where(array('sizes' => 'M'))->all();
echo 'WHERE(): PRODUCTS (sizes=M):';
var_dump($data);
echo '<hr>';

// Get entries with 'L' and 'XL' sizes
$data = $db->table('products')->where(array('sizes' => array('L', 'XL')))->all();
echo 'WHERE(): PRODUCTS (sizes=L AND XL):';
var_dump($data);
echo '<hr>';

// Get the count of entries with 'L' size
$data = $db->table('products')->where(array('sizes' => 'L'))->count();
echo 'WHERE(): NUMBER OF PRODUCTS (sizes=L):';
var_dump($data);
echo '<hr>';

// Init second database
$db_dev = new FlatDB(__DIR__ . '/data', 'dev');
echo '<h2>SECOND DATABASE (dev)</h2>';
echo '<hr>';
$db_dev->table('subscribers')->insert(array('email' => 'john@example.com' ));
$db_dev->table('subscribers')->insert(array('email' => 'ann@example.com' ));
$db_dev->table('subscribers')->insert(array('email' => 'james@example.com' ));
$db_dev->table('subscribers')->insert(array('email' => 'gordon@example.com' ));
$db_dev->table('subscribers')->insert(array('email' => 'lisa@example.com' ));
// Get all entries from table
$data = $db_dev->table('subscribers')->all();
echo 'SUBSCRIBERS:';
var_dump($data);
echo '<hr>';
?>
</body>
</html>
