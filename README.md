# Nox
Nox is a minimal-dependency bearing PHP v8 web framework with minimally invasive syntax. There's no new syntax to learn for arguments and there isn't a huge depth of depedencies. The code is transparent and leaves a small footprint - to make debugging significantly easier.

## Installing
Use composer

```bash
composer require nox7/nox
```

## Setting up a Project
From your project's root directory, make a new directory for your working and serving code. This will be your website's document root. Commonly, just name it `app` or `nox-app`. Your root folder structure should be look like this
```
| /root
| - /app
| - /vendor
| - composer.json
| - composer.lock
```

From the command line, change directories into `/app`

```
$ cd app
```

Then, run the script to make a project in that directory

```
php ../vendor/nox7/nox/src/Scripts/make-sample-project.php
```

This will create a fully working sample project to begin your Nox app/project off of. Make sure your web server is set to have the `/app` folder as your document root.

## Adding a Route
If you have successfully made the sample project with the CLI instructions above, you will have a folder named `/app/controllers` with a `HomeController.php` in it.

It has the following source code

```php
<?php

	// No need to require the autoload in the Controller.
	// The controller has the scope of the request.php file

	use Nox\Router\Attributes\Route;

	class HomeController extends \Nox\Router\BaseController{

		#[Route("GET", "/")]
		public function homeView(): string{
			return \Nox\RenderEngine\Renderer::renderView("home.html");
		}
	}
```

Routes use PHP8's attributes. You can specify a request method (GET, POST, PATCH, DELETE, etc) and a URI route.

Any classes in the controllers folder will be automatically loaded and processed as classes with routes. You can clone the HomeController.php and rename it to organize your routes into seperate classes and files.

The name of a route method (in the above example, `homeView()`) is irrelevant and has no affect on routes. Feel free to name them as you like.

## Specifying a Regex Route and URI Parameters
To use a regular expression as your route, the `Route` attribute will accept a third parameter - a boolean.

```php
#[Route("GET", "/\/book\/(?<bookID>\d+)/", true)]
```

By using name capture groups, these parameters will be injected into the $_GET super global. Going off of the above book route (which will match for `/book/1` or any digit):

```php
$bookID = (int) $_GET['bookID'];
```

## Adding Custom Route Attributes
To make duplicate code less abundant, you can create custom attributes that the Nox router will support and check. For example, you could have an attribute that forces a user to be logged in.

```php
#[Route("GET", "/user/settings")]
#[RequireLogin()]
```

Your attribute should implement the RouterAttribute from the Nox library. It requires you to implement a `getAttributeResponse` method that should return an AttributeResponse. This allows attributes to simply allow a route to be passed over or chosen based on custom logic.

You can also force HTTP response codes or silent route request rewrites (such as a 403 when a user is not logged in).

```php
<?php

    use \Nox\Router\Interfaces\RouteAttribute;
    use \Nox\Router\AttributeResponse;

    // Set the PHP attribute to only be used on class methods
    #[Attribute(Attribute::TARGET_METHOD)]
    class RequireLogin implements RouteAttribute{
        public AttributeResponse $attributeResponse;
    
        public function __construct(){
            // By default, tell the response this route is usable
            $this->attributeResponse = new AttributeResponse(
                isRouteUsable: true
            );
        
            // Check if the user is logged in
            $user = ?;
            if ($user->isLoggedIn()){
                $this->attributeResponse->isRouteUsable = true;
            }
            
            // Tell the router this route is not usable
            // and a 403 should be given
            $this->attributeResponse->isRouteUsable = false;
            $this->attributeResponse->responseCode = 403;
        }
        
        public function getAttributeResponse(): AttributeResponse{
            return $this->attributeResponse;
        }
    }
```

With this, you can now use the `#[RequireLogin()]` on your routes to require a user (whatever use class you make yourself) be logged in. Otherwise, that route will not pass.

## Layout and View File Syntax
The `HomeController.php` example gives you a brief snippet of how to render a view file in your views config directory.

All views require a layout file to be specified in them. This layout file will be the **barebones** HTML framework for every view file to be rendered in. Here is an example of a base layout file named `base.php` and saved in your `/app/layouts` folder.

```php
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?= $htmlHead ?>
</head>
<body>
	<?= $htmlBody ?>
</body>
</html>
```

The variables `$htmlHead` and `$htmlBody` come from the Nox view engine and represent a rendered view file's respective Head and Body sections.

The syntax of view files is relatively short, and minimal. You simply specify the layout file to use, and then the HTML head and body sections.

```html
@Layout = "base.php"
@Head{
    <title>Home Page</title>
}
@Body{
    <main>
        <h1>Home page</h1>
        <p>
            Nox web framework home page.
        </p>
    </main>
}
```

## Passing Data from Your Controller to View
When using the Nox rendering engine, you will commonly see a render call in a controller such as this
```php
return \Nox\RenderEngine\Renderer::renderView("home.html");
```

Which would render the `home.html` view file in the `/app/views` directory.

However, there is an optional second array argument you can provide that will pass the data into the view to be used. This data is not serialized, so you can pass abstract data such as objects.

```php
return \Nox\RenderEngine\Renderer::renderView(
    "home.php",
    [
        "testVariable"=>"hello!"
    ],
);
```

Then, in your view file (`home.php` in this case, notice the file name was changed from .html to .php to reflect there is now PHP code in the file) you can access the array with the variable `$viewScope`.

```php
<?php
    $testVariable = $viewScope['testVariable'];
?>
@Layout = "base.php"
@Head{
    <title>Home Page</title>
}
@Body{
    <main>
        <h1>Home page</h1>
        <p>
            Nox web framework home page.
        </p>
        <p>
            <?= $testVariable ?>
        </p>
    </main>
}
```

With this method, you can easily pass user data or object data to render on the view - such as a user's name or a book's content.

## The Abyss ORM
See: https://github.com/nox7/nox/tree/main/src/ORM/README.md