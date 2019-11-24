<?php

namespace App\Http\Controllers;

use App\Menu;
use Illuminate\Http\Request;
use Twilio\Rest\Client;

class MenuController extends Controller
{
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
