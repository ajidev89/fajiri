<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\AuthRepositoryInterface;
use App\Http\Resources\User\UserResource;
use App\Http\Traits\AuthUserTrait;
use App\Http\Traits\ResponseTrait;
use App\Jobs\Otp\SendOneTimePasswordJob;
use App\Models\Otp;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthRepository implements AuthRepositoryInterface {

    use ResponseTrait, AuthUserTrait;

    public function __construct(protected User $model,protected Role $role, protected Profile $profile, protected Otp $otp) {
    }

    public function register($request){

        DB::beginTransaction();

        try{

            $validAppNames = ['personal', 'business', 'back-office'];

            $appName = $request->header('X-App-Name');

            $phone = decryptToken($request->phone['token']);
            $email = decryptToken($request->email['token']);

            $slug = ($appName == "personal") ? "user" : "business-owner";

            $role = $this->role->where('slug', $slug)->first();


            $user = $this->model->create([
                "email" => $request->email['value'],
                "phone" => $request->phone['value'],
                "account_type" => $appName,
                "password" => Hash::make($request->password),
                "role_id" => $role->id
            ]);


            if($phone && $phone['value'] === $request->phone['value']) {
                $user->markPhoneAsVerified();
            }

            if($email && $phone['value'] === $request->email['value']) {
                $user->markEmailAsVerified();
            }

            $user->profile()->create([
                "first_name" => $request->first_name,
                "last_name" => $request->last_name,
                "dob" => $request->dob,
            ]);

            $user->address()->create([  
                "line_1" => $request->line_1,
                "line_2" => $request->line_2,
                "city" => $request->city,
                "state" => $request->state,
                "postal_code" => $request->postal_code ?? null, 
                "country_id" => $request->country_id
            ]);

            DB::commit();

            return $this->handleSuccessResponse("Successfully registered",[
                "user" => new UserResource($user)
            ]);
            
        }

        catch(Exception $e) {
            DB::rollBack();
            return $this->handleErrorResponse($e->getMessage(), 400);
        }

     
    }

    public function login($request)
    {

        $field = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $credentials = $request->only($field, 'password');


        if (Auth::attempt($credentials)) {

            if($request->app_name != $this->user()->account_type){
                
                return $this->handleErrorResponse('Your account does not have the necessary permissions for this application. Please log in with the appropriate credentials.',401);
            }

            // if($this->user()->status != "active"){
            //     return $this->handleErrorResponse('Your account is not active contact support via email',401);
            // }

            $this->user()->tokens()->delete();
         
            $code = random_int(100000, 999999);

            $otp = $this->otp->create([
                'identifier' => $request->$field,
                'channel' => $field,
                'hash' => Hash::make($code),
            ]);

            if($request->notification_token){
                $this->user()->update(['notification_token' => $request->notification_token]);
            }

            SendOneTimePasswordJob::dispatchAfterResponse($otp, $code);

            return $this->handleSuccessResponse('Successfully sent otp',new UserResource($this->user())); 
        }

        return $this->handleErrorResponse('Invalid login credentials',401);
    }

    public function changePassword($request){

        try{
            $data = decryptToken($request->token);

            $field = filter_var($data['value'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
     
            $user = $this->model->where($field, $data['value'])->firstorFail();
     
            $user->forceFill([
                'password' => Hash::make($request->password),
                'remember_token' => Str::random(60),
            ])->save();
     
            event(new PasswordReset($user));

            return $this->handleSuccessResponse('Your password has been sucessfully changed!');
        }

        catch(Exception $e){
            return $this->handleErrorResponse('We could not change your password please try again!'); 
        }
    }

    public function generateToken($request)
    {
        $request->fulfill();

        $user = $this->model->where($request->channel, $request->identifier)->firstorFail();

        $token = $user->createToken($user->email)->plainTextToken;

        return $this->handleSuccessResponse('Successfully verified otp', [
            "token" => $token,
            "type" => "bearer"
        ]);
    }


    public function logout(){

        $this->user()->tokens()->delete();

        return $this->handleSuccessResponse("Successfully logged out");
    }

}