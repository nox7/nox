# Nox: The Abyss ORM
Abyss is a short-sighted and no-additional-syntax MySQL ORM. 

## Setting Up NoxEnv for Abyss
If you've followed the tutorial from this repository's root README.md file, then you will have an `/app` (or similarly named) folder in your application's root. Either way, your Nox working directory should have a `nox-env.php` file.

This file houses the environment variables for the Abyss ORM (as well as any other custom data you'd like to add yourself).

```php
<?php
	class NoxEnv{
		const DEV_ENV = "development";
		const MYSQL_HOST = "localhost";
		const MYSQL_PORT = "3306";
		const MYSQL_USERNAME = "root";
		const MYSQL_PASSWORD = "";
		const MYSQL_DB_NAME = "test";
	}
```

## Loading Abyss
If you are using the Nox routing framework, then **Abyss is already loaded** for you. You can simply begin using it.

If you are using Abyss stand-alone, such as making a script to synchronize models, then you must load it yourself. 

Should you have created a Nox project via the CLI script, then you can find an example of this in `/app/cli-scripts/sync-models.php`.

To load Abyss **standalone**, simply set the config statically.

```php
    require_once __DIR__ . "/../../vendor/autoload.php";

    use \Nox\ORM\Abyss;
    
    // This should be the path to the Nox project root directory. (Not /, but /app)
    $pathToRootDirectory = __DIR__ . "/..";
    Abyss::loadConfig($pathToRootDirectory);
```

You can then instantiate Abyss and utilize it.

## Abyss Models
A model is a table and column definition that you can use to keep your project's MySQL tables and columns in sync no matter where you deploy the project.

Additionally, models help you relate classes (such as a User class) to a model and make instantiating users, saving, and updating them in pure PHP without MySQL syntax easy.

If you've followed the root repository's README, then you will have a `/app/models` directory with an example User model in it.

Your models directory is specified by the Nox config in `/app/nox.json`. It can be changed if you'd like.

An example of a User model is as follows

```php
<?php

	use \Nox\ORM\ColumnDefinition;
	use \Nox\ORM\Interfaces\MySQLModelInterface;
	use \Nox\ORM\MySQLDataTypes\Integer;
	use \Nox\ORM\MySQLDataTypes\VariableCharacter;

	class UsersModel implements MySQLModelInterface {

		/**
		 * The name of this Model in the MySQL database as a table
		 */
		private string $mysqlTableName = "users";

		/**
		 * The string name of the class this model represents and can instantiate
		 */
		private string $representingClassName = "User";

		public function getName(): string{
			return $this->mysqlTableName;
		}

		public function getInstanceName(): string{
			return $this->representingClassName;
		}

		public function getColumns(): array{
			return [
				new ColumnDefinition(
					name:"id",
					classPropertyName: "id",
					dataType : new Integer(),
					defaultValue: null,
					autoIncrement: true,
					isPrimary: true,
				),
				new ColumnDefinition(
					name:"name",
					classPropertyName: "name",
					dataType : new VariableCharacter(65),
					defaultValue: "",
				),
				new ColumnDefinition(
					name:"email",
					classPropertyName: "email",
					dataType : new VariableCharacter(65),
					defaultValue:"",
				),
				new ColumnDefinition(
					name:"creation_timestamp",
					classPropertyName: "creationTimestamp",
					dataType : new Integer(),
				),
			];
		}
	}
```

## Instantiating a Class From a Model
Following the example that a User is a model and a class (UserModel, and User), then you can use Abyss to instantiate a blank user class from the model.

```php
$newUser = $abyss->instanceFromModel(User::getModel());

// $newUser is now a blank user, but has not been saved/inserted to the database.

var_dump($newUser->id); // Will be NULL because we set the defaultValue in the model to be null

$newUser->save(); // save() methods on classes that implement the ModelInstance interface
// save() will EITHER insert or update the user

var_dump($newUser->id); // Now outputs int(1)

// Change the user's name
$newUser->name = "New name!";

// Save the change to MySQL
$newUser->save();

// You can also delete the user from MySQL entirely
$newUser->delete();

// Deletion happens by using the primary key on a class' model. Should a class model not have one, it cannot be deleted this way.
```

## Querying
Say you would like to fetch all users. We can do this with `Abyss::fetchInstances`
```php
$abyss = new Abyss();

// Get the model and query objects needed to run a fetch
$userModel = User::getModel();

$arrayOfUsers = $abyss->fetchInstances($userModel);
```

You can also add query parameters. Such as a WHERE clause, ORDER clause, or pagination (LIMIT with OFFSET).

Below will find all users with an email, limit the result to 5 results on page 1, and order them by name.
```php
$abyss = new Abyss();

// Get the model and query objects needed to run a fetch
$userModel = User::getModel();
$columnQuery = (new \Nox\ORM\ColumnQuery)
    ->where("email", "=", "test@example.com");
$resultOrder = (new \Nox\ORM\ResultOrder())
    ->by("name", "asc");
$pager = (new \Nox\ORM\Pager(
    pageNumber: 1, 
    limit: 5
    ));

$arrayOfUsers = $abyss->fetchInstances(
    $userModel,
    $columnQuery,
    $resultOrder,
    $pager
);
```

More complicated column queries (WHERE clauses) such as grouping conditionals (`WHERE (x and y) or (a and b)`) can be done with the ColumnQuery as well.

```php
$columnQuery = (new \Nox\ORM\ColumnQuery)
    ->startConditionGroup() // Begins a parenthesis group
    ->where("email", "=", "test@example.com")
    ->and()
    ->where("name", "LIKE", "%nox%")
    ->endConditionGroup()
    ->or()
    ->where("name", "=", "nox7");
```

## Raw Queries
To access the MySQL connection itself and perform raw operations on the `mysqli` object, then you can call `getConnection()`

```php
$abyss = new Abyss();
$mysqli = $abyss->getConnection();
```