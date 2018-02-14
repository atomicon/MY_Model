# MY_Model

A DataMapper-ORM like model for CodeIgniter

## Example usage:

Assuming you have a table "users"
and a model: ```class User_model extends MY_Model { ... }```

## Load a single item

```php
//create instance
$user = new User_model();

// construct where
$user->where('id', 1);

// get user from table
$user->get();

// check if user is available
if ($user->exists())
{
	echo $user->id;
	echo $user->email;
}
```

## Load multiple items

```php
//create instance
$users = new User_model();

// limit users to 10
$users->limit(10);

// get users from table
$users->get();

// check if user is available
if ($users->exists())
{
	// grab the "all" property
	foreach($users->all as $user)
	{
		echo $user->id;
		echo $user->email;
	}
}
```

## Insert an item

```php
$user = new User_model();

$user->email = 'info@atomicon.nl';

if ($user->save())
{
	echo 'inserted:' . $user->id;
}
else
{
	echo 'error';
}
```

## Update an item

```php
//create instance
$user = new User_model();

// construct where
$user->where('id', 1);

// get user from table
$user->get();

// check if user is available
if ($user->exists())
{
	$user->email = 'info@atomicon.nl';
	if ($user->save())
	{
		echo 'updated:' . $user->id;
	}
	else
	{
		echo 'error';
	}
}
```

## Delete an item 

```php
//create instance
$user = new User_model();

// construct where
$user->where('id', 1);

// get user from table
$user->get();

// check if user is available
if ($user->exists())
{
	if ($user->delete())
	{
		echo 'deleted user';
	}
	else
	{
		echo 'error';
	}
}
```

## Insert an item with POST data

```php
//create instance
$user = new User_model();

//update all properties of the data object with a POST array
$user->fill( $this->input->post() );

//save user
if ($user->save())
{
	echo 'ok';
}
else
{
	echo 'error';
}
```

## Fill a dropdown box

```php
//create instance
$users = new User_model();

// get all users from table
$users->get();

if ($users->exists())
{
	echo form_dropdown('user_id', $users->as_options('id', 'email'), '');
}
```

## Using the magic ```__get_``` function to access properties

in your User_model create a functions:

```php
function __get_url()
{
	return 'http://www.atomicon.nl';
}

function __get_children()
{
	$children = new User_model();
	$children->where('parent_id', $this->id);
	$children->get();
	return $children;
}
```

and use it in your code like:

```php
//create instance
$user = new User_model();

//get the url property (via __get_url)
echo $user->url; // Output: http://www.atomicon.nl

//get children
var_dump($user->children);
```
