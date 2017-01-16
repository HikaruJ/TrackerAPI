<?php

namespace App\Http\Controllers;

use App\User;
use App\Http\Requests\CreateUserRequest;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    //////////////////////////
    /* Public Functions */

    /**
    * Create a new User instance and return the User UUID Id
    * In case the Email or OutlookId from the Client is empty, return an empty response
    * In case the User exists, return the User UUID Id 
    *
    * @param  CreateUserRequest $request
    * @return Response
    */
    public function store(CreateUserRequest $request) 
    {
        $result = null;

        $email = $request->email;
        if (is_null($email) || empty($email)) 
        {
            return $result;
        }

        $email = strtolower($email);
        $outlookId = $request->outlookId;
        if (is_null($outlookId) || empty($outlookId)) 
        {
            return $result;
        }

        $user = User::getUserByEmail($email);
        if (!is_null($user)) {
            if ($user->outlookId != $outlookId) 
            {
                $user->update([
                    'outlook_id' => $outlookId
                ]);
            }

            $result = $user->id;
            return $result;
        }

        $user = new User;
        $user->email = $email;
        $user->outlookId = $outlookId;
        $user->save();

        $result = $user->id;
        return $result;
    }
    //////////////////////////
}