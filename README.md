## Building a food ordering service with Twilio SMS
In this tutorial, I will teach you how to use [Twilio’s Programmable SMS](https://www.twilio.com/sms) to create an SMS based food ordering service using [Laravel](https://laravel.com/). After we’re finished, your user’s will be able to place orders for food items via SMS.

## Prerequisites

In order to follow this tutorial, you will need:

- Basic knowledge of Laravel
- [Laravel](https://laravel.com/docs/master) installed on your local machine
- [Composer](https://getcomposer.org/) globally installed
- A [Twilio Account](https://www.twilio.com/try-twilio?promo=B2YAW1)

## Getting Started

Start off by creating a new Laravel project. This can be done using either the [Laravel installer](https://laravel.com/docs/6.x#installation) or [Composer](https://getcomposer.org/). We will be making use of the Laravel installer in this tutorial. If you don’t have it installed, you can check out how to do so from the [Laravel documentation](https://laravel.com/docs/master).

To generate a fresh Laravel project, run the following command on your terminal:

    $ laravel new twilio-food-ordering

Next, proceed to install the [Twilio SDK](https://www.twilio.com/docs/libraries/php) for PHP. Change your working directory to the new project generated `twilio-food-ordering` and install the Twilio SDK via Composer:

    $ composer require twilio/sdk 

If you don’t have Composer installed on your local machine you can do so by following the instructions in [their documentation](https://getcomposer.org/doc/00-intro.md).

### Setting up the Twilio SDK
The [Twilio SDK](https://www.twilio.com/docs/libraries) requires your Twilio credentials to authenticate each request. You can retreive these credentials from the [Twilio dashboard](https://wwww.twilio.com/console). Head over to your [dashboard](https://www.twilio.com/console) and grab your `account_sid` and `auth_token`.

![](https://paper-attachments.dropbox.com/s_14AED1E729777868A76C728380D4E7434CFBFCFA0C71AD83ED009C3DCFE403E8_1574552733012_Group+8.png)

Now navigate to the [Phone Number](https://www.twilio.com/console/phone-numbers/incoming) section to get your SMS enabled phone number.

![](https://paper-attachments.dropbox.com/s_14AED1E729777868A76C728380D4E7434CFBFCFA0C71AD83ED009C3DCFE403E8_1574552749835_Group+9.png)

If you don’t have an active number, you can easily create one [here](https://www.twilio.com/console/phone-numbers/search). This is the phone number we will use for sending and receiving SMS via Twilio.

Next update your `.env` file with your Twilio credentials. Open `.env` located at the root of the project directory and add these values:

    TWILIO_SID="INSERT YOUR TWILIO SID HERE"
    TWILIO_AUTH_TOKEN="INSERT YOUR TWILIO TOKEN HERE"
    TWILIO_NUMBER="INSERT YOUR TWILIO NUMBER IN [E.164] FORMAT"

## Setup the Database

At this point, you have successfully setup your Laravel project with the Twilio SDK installed. Now proceed to setting up your database for the application. We will make use of the [MySQL](https://www.mysql.com/) database in this tutorial. If you use a MySQL client like [phpMyAdmin](https://www.phpmyadmin.net/) to manage your database, then go ahead and create a database named `food_ordering` and skip this section. If not, install MySQL from the [official site](https://www.mysql.com/downloads/) for your platform of choice. After successful installation fire up your terminal and run this command to login to MySQL:

    $ mysql -u {your_user_name}

***NOTE:** Add the -p flag if you have a password for your mysql instance.*

Once you are logged in, run the following command to create a new database:

    mysql> create database food_ordering;
    mysql> exit;

Next, update your `.env` file with your database credentials. Open up `.env` and make the following adjustments:

    DB_DATABASE=food_ordering
    DB_USERNAME={username}
    DB_PASSWORD={password if any}

### Create Migration

Now that you have successfully created your application database, proceed to create the needed [migration](https://laravel.com/docs/6.x/migrations) and it’s respective [model](https://laravel.com/docs/6.x/eloquent) for the application. To do this, run the following command at the root directory of the project:

    $ php artisan make:model Menu --migration

This will generate an [eloquent model](https://laravel.com/docs/6.x/eloquent) named `Menu` and a migration file `{current_time_stamp}_create_menus_table` at the `/database/migrations` directory.

Open up the project folder in your favorite IDE/text editor so that you can begin making the needed adjustments. Open up your menu migration file (`database/migrations/2019_11_24_031320_create_menus_table.php` ) and make the following changes to the `up()` method:

    /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            Schema::create('menus', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string("name");
                $table->string("price");
                $table->timestamps();
            });
        }

Run the following command to generate your migration:

    $ php artisan migrate

After successful migration the `menus` table will be created in your database with the listed columns.

### Seeding the Database

Next you will need some sample data in your database which will serve as the available menu items when a user *requests* a menu. You can proceed to do this manually or you could setup [seeders](https://laravel.com/docs/6.x/seeding) for your database. Seeders will help auto fill the database with dummy menu items. To do this, first generate a seeder class using the `artisan` command:

    $ php artisan make:seeder MenuTableSeeder

Now, open up the newly created file (`database/seeds/MenuTableSeeder.php`) and make the following changes:

    <?php
    use Illuminate\Database\Seeder;
    use Illuminate\Support\Facades\DB;
    class MenuTableSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            DB::table('menus')->insert([
                [
                    'name' => "Nigerian Jollof Rice and Chicken",
                    'price' => "100",
                ],
                [
                    'name' => "Burger and Coke",
                    'price' => "50",
                ],
                [
                    'name' => "Chicken and Chips",
                    'price' => "30",
                ],
                [
                    'name' => "Ghana Jollof Rice and Water",
                    'price' => "5",
                ],
            ]);
        }
    }
    
This will create four dummy menu items in your database to serve as the available menu items for this application. Now run the following command to seed your database:

    $ php artisan db:seed --class=MenuTableSeeder


## Placing Order

At this point, you should have your Laravel project set up and your database seeded with dummy data. This next section will cover implementing the needed functionalities for placing an order. Open up your terminal and run the following command to generate a [controller](https://laravel.com/docs/6.x/controllers) to house the logic for placing orders and also sending out responses to your users:

    $ php artisan make:controller MenuController

Open the newly created file `app/Http/Controllers/MenuController.php` and make the following changes:

    <?php
    namespace App\Http\Controllers;
    use App\Menu;
    use Illuminate\Http\Request;
    use Twilio\Rest\Client;
    class MenuController extends Controller
    {
        /**
         * commandHandler for received SMS.
         *
         * @param  Request  $request
         * @return Response
         */
        public function commandHandler(Request $request)
        {
            $from = $request->input("From");
            $body = strtolower($request->input("Body"));
            if ($body == 'menu') {
                $items = Menu::all(['id', 'name', 'price']);
                $response = $this->formatItems($items);
                $response .= "\n\r To place order, use the format (e.g): \n\r";
                $response .= "no: 1,2,3 \n address: I want my order to come here";
            } else if (strpos($body, 'no:') === 0) {
                /* Extract items ids from text body */
                $items = substr($body, strpos($body, "no:") + 3, strpos($body, "address:") - 3);
                /* Extract address from text body */
                $address = strstr($body, "address");
                /* Find items with ids */
                $items = Menu::findMany(explode(",", $items), ['id', 'name', 'price']);
                $total = $items->sum('price');
                $response = $this->formatItems($items);
                $response .= "\n\r Total: $$total";
                $response .= "\n\r " . ucfirst($address);
            } else {
                $response = "Invalid command sent. \n\n Available commands: \n";
                $response .= "1. menu \n";
            }
            $this->sendMessage($response, $from);
            return "message received";
        }
        /**
         *  Formats array from db to user friendly string
         */
        private function formatItems($items)
        {
            $response = "";
            foreach ($items as $item) {
                $str = "$item->id. $item->name | $$item->price";
                $response .= $str . "\n\r \n\r";
            }
            return $response;
        }
        /**
         *  Sends sms to user using Twilio's programmable sms client
         */
        private function sendMessage(string $message, string $recipients)
        {
            $account_sid = getenv("TWILIO_SID");
            $auth_token = getenv("TWILIO_AUTH_TOKEN");
            $twilio_number = getenv("TWILIO_NUMBER");
            $client = new Client($account_sid, $auth_token);
            $client->messages->create($recipients, array('from' => $twilio_number, 'body' => $message));
        }
    }
    
Thats alot of code! Let’s break it down. First take a look at the `sendMessage()` function:

    /**
         *  Sends sms to user using Twilio's programmable sms client
         */
        private function sendMessage(string $message, string $recipients)
        {
            $account_sid = getenv("TWILIO_SID");
            $auth_token = getenv("TWILIO_AUTH_TOKEN");
            $twilio_number = getenv("TWILIO_NUMBER");
            $client = new Client($account_sid, $auth_token);
            $client->messages->create($recipients, array('from' => $twilio_number, 'body' => $message));
        }

The function takes in two parameters, `$message` (the *response* to be sent to the user) and `$recipients` (the user’s phone number which the message is to be sent to). After creating a new instance of the Twilio SDK client with the Twilio credentials stored in your `.env` file, an SMS with the `$message` as body is sent to the `$recipients` using the `messages→create()` from the Twilio client. The `messages→create()` function takes in two parameters of either a receiver or *array* of receivers of the message and an *array* with the properties of `from` and `body` where `from` is your active Twilio phone number and `body` is the *message* that’s to be sent to the *recipient(s)*. 

Next let’s look at the `commandHandler()` method:

      /**
         * Command handler for received SMS.
         *
         * @param  Request  $request
         * @return Response
         */
        public function commandHandler(Request $request)
        {
            $from = $request->input("From");
            $body = strtolower($request->input("Body"));
            if ($body == 'menu') {
                $items = Menu::all(['id', 'name', 'price']);
                $response = $this->formatItems($items);
                $response .= "\n\r To place order, use the format (e.g): \n\r";
                $response .= "no: 1,2,3 \n address: I want my order to come here";
            } else if (strpos($body, 'no:') === 0) {
                /* Extract items ids from text body */
                $items = substr($body, strpos($body, "no:") + 3, strpos($body, "address:") - 3);
                /* Extract address from text body */
                $address = strstr($body, "address");
                /* Find items with ids */
                $items = Menu::findMany(explode(",", $items), ['id', 'name', 'price']);
                $total = $items->sum('price');
                $response = $this->formatItems($items);
                $response .= "\n\r Total: $$total";
                $response .= "\n\r " . ucfirst($address);
            } else {
                $response = "Invalid command sent. \n\n Available commands: \n";
                $response .= "1. menu \n";
            }
            $this->sendMessage($response, $from);
            return response("message received");
        }

This function handles the next interaction after an SMS has been received from the user. After receiving a text message from the user, Twilio makes a POST request to your application via the webhook URL set in your console with the `From` and `Body` of the SMS. You can then return an appropriate response to the user depending on what was gotten from the `Body` of the SMS. In this case, only two(2) format of texts are expected, either `menu`  - which returns the available menu items with their corresponding *ids* - or a *string* containing the `no:` - followed by a comma separated list of the `ids` of the menu item a user wants included in their order:

        if ($body == 'menu') {
                $items = Menu::all(['id', 'name', 'price']);
                $response = $this->formatItems($items);
                $response .= "\n\r To place order, use the format (e.g): \n\r";
                $response .= "no: 1,2,3 \n address: I want my order to come here";
            } else if (strpos($body, 'no:') === 0) {
                /* Extract items ids from text body */
                $items = substr($body, strpos($body, "no:") + 3, strpos($body, "address:") - 3);
                /* Extract address from text body */
                $address = strstr($body, "address");
                /* Find items with ids */
                $items = Menu::findMany(explode(",", $items), ['id', 'name', 'price']);
                $total = $items->sum('price');
                $response = $this->formatItems($items);
                $response .= "\n\r Total: $$total";
                $response .= "\n\r " . ucfirst($address);
            } else {
                $response = "Invalid command sent. \n\n Available commands: \n";
                $response .= "1. menu \n";
            }

If the `Body` of the text is equal to `menu`, then the database is queried to return all menu items in a more user readable *text* using the `formatItems()` helper method. At this point it is also a good idea to let the user know how to place an order, so a *tip* on how to place an order is also appended to the `$response` before it is sent to the user. In cases where the user actually places an order using the correct format, the ids of the items are extracted from the text body and then used to query the database. After successfully querying the database and formatting the query results, the *total cost* of the items is appended to the `$response` along side the delivery *address* which was sent by the user.

After preparing the appropriate response, an SMS is sent back to the user whose number was supplied in the `From` input data using the `sendMessage()` method.

***NOTE:** A simple format (`no: 1,2,3 \n address: I want my order to come here`) is used for placing an order, as it is important to know how to extract the items ids and also the delivery address for the order. If this isn’t enforced you might encounter issues when trying extract the needed data from the SMS body.*

## Creating Application Route

Having successfully written the logic for placing an order, the next step is to create a route which will call the `commandHandler()` function in the controller. To do this, open `routes/web.php` and make the following changes:

    <?php
    /*
    |--------------------------------------------------------------------------
    | Web Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register web routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | contains the "web" middleware group. Now create something great!
    |
     */
    Route::post("/order", "MenuController@commandHandler");
    
Since you won’t be accessing this route from a form, it is important to include the route to the `except` array of `VerifyCsrfToken` middleware. This allows access to the route without providing a [csrf](https://laravel.com/docs/6.x/csrf) token. Open up `app/Http/Middleware/VerifyCsrfToken.php` and add the `/order` route to the `except` array:

    <?php
    namespace App\Http\Middleware;
    use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
    class VerifyCsrfToken extends Middleware
    {
        /**
         * Indicates whether the XSRF-TOKEN cookie should be set on the response.
         *
         * @var bool
         */
        protected $addHttpCookie = true;
        /**
         * The URIs that should be excluded from CSRF verification.
         *
         * @var array
         */
        protected $except = [
            "/order"
        ];
    }

## Setting up Webhook For Sending Responses

To enable responding to messages sent to you via your Twilio phone number, you have to properly configure your Twilio phone number to handle incoming SMS messages. Fortunately, Twilio supports several [channels](https://support.twilio.com/hc/en-us/articles/223136047-Configuring-Phone-Numbers-to-Receive-and-Respond-to-SMS-and-MMS-Messages) for doing this, but for this application, [webhooks](https://www.twilio.com/docs/glossary/what-is-a-webhook) will be used. 

### Exposing Your Application To Internet

To allow access to your Laravel project through a webhook, your application has to be accessible via the internet. This can easily be achieved using [ngrok](https://ngrok.com/).

> ngrok allows you to expose a web server running on your local machine to the internet

If you don’t have [ngrok](https://ngrok.com/) set up on your computer, head over to their [official download page](https://ngrok.com/download) and follow the instructions to get it installed on your machine. If you already have it set up, then open up your terminal and run the following commands to start your Laravel application and expose it to the internet:

    $ php artisan serve 

Take note of the port your application is currently running on (usually `8000`) after running the above command. Now open another instance of your terminal and run this command:

    $ ngrok http 8000 

After successful execution of the above command, you should see a screen like this:

![](https://camo.githubusercontent.com/b81d4c4aa2104e5545f8fa48269c578454960697/68747470733a2f2f70617065722d6174746163686d656e74732e64726f70626f782e636f6d2f735f463742413245463337393739433442463434423541413142393230374438443345433945444445323746423944373130444443393944443242434234373333385f313536303637323039383733315f53637265656e73686f742b66726f6d2b323031392d30362d31362b30382d35372d32382e706e67)


Take note of the `forwarding` URL as we will be making use of it next.

### Updating Twilio phone number configuration
Head over to the [active phone number](https://www.twilio.com/console/phone-numbers/incoming) section in your Twilio console and select the active phone number used for your application. Next, scroll down to the Messaging segment and update the webhook URL for the field labeled *"A MESSAGE COMES IN"* as shown below:

![](https://paper-attachments.dropbox.com/s_14AED1E729777868A76C728380D4E7434CFBFCFA0C71AD83ED009C3DCFE403E8_1574602377427_Group+12.png)

## Testing

Great! At this stage you must have completed the logic for placing orders and also configured your phone number for receiving SMS. Now proceed to testing your application.

### Testing Application

To do this, simply send a text message with a body of `menu` to your active Twilio number. You should receive a response shortly after with the menu items and their respective prices. After which, you can select any number from the list and send back an SMS in this format *"no: {comma separated item ids}  address: {your delivery address}"* to place an order. If all goes well you should receive a summary of your order back.

## Conclusion

At this point, you should have a working food ordering system powered by Twilio SMS. By doing so you have learned how to integrate Twilio Programmable SMS in a Laravel application and also how to respond to text messages sent to your Twilio phone number(s), alongside exposing your Laravel application from your local machine to the internet. If you would like to take a look at the complete source code for this tutorial, you can find it on [Github](https://github.com/thecodearcher/food-ordering-service-via-sms).

You can also take this further by confirming order(s) placed via automated voice call using the [Twilio Voice API](https://www.twilio.com/docs/voice).

I’d love to answer any question(s) you might have concerning this tutorial. You can reach me via:

- Email: [brian.iyoha@gmail.com](mailto:brian.iyoha@gmail.com)
- Twitter: [thecodearcher](https://twitter.com/thecodearcher)
- GitHub: [thecodearcher](https://github.com/thecodearcher)
