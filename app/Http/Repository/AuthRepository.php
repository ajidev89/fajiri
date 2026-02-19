<?php

namespace App\Http\Repository;

use App\Enums\Kyc\Status;
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
use Google_Client;
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

            if ($request->input('phone.token')) {
                $phone = decryptToken($request->input('phone.token'));
            }

            if ($request->input('email.token')) {
                $email = decryptToken($request->input('email.token'));
            }

            $role = $this->role->where('slug', "user")->first();

            $user = $this->model->create([
                "email" => $request->email['value'] ?? null,
                "phone" => $request->phone['value'] ?? null,
                "account_type" => $request->account_type,
                "sub_account_type" => $request->sub_account_type,
                "password" => Hash::make($request->password),
                "role_id" => $role->id
            ]);


            if($phone && $phone['value'] === $request->phone['value']) {
                $user->markPhoneAsVerified();
            }

            if($email && $email['value'] === $request->email['value']) {
                $user->markEmailAsVerified();
            }

            $user->profile()->create([
                "first_name" => $request->first_name,
                "last_name" => $request->last_name,
                "dob" => $request->dob,
                "gender" => $request->gender,
                "address" => $request->address,
                "occupation" => $request->occupation,
                "avatar" => $request->avatar,
            ]);                                 

            DB::commit();

            return $this->handleSuccessResponse("Successfully registered",[
                "user" => new UserResource($user)
            ]);
            
        }

        catch(Exception $e) {
            info($e);
            DB::rollBack();
            return $this->handleErrorResponse($e->getMessage(), 400);
        }

     
    }

    public function login($request)
    {

        $field = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $credentials = $request->only($field, 'password');


        if (Auth::attempt($credentials)) {

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

    public function loginWithGoogle($request)
    {
        $client = new Google_Client([
            'client_id' => config('services.google.client_id')
        ]);

        $payload = $client->verifyIdToken($request->id_token);

        if (!$payload) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = User::updateOrCreate(
            ['email' => $payload['email']],
            [
                'name' => $payload['name'],
                'google_id' => $payload['sub'],
            ]
        );

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }


    public function logout(){

        $this->user()->tokens()->delete();

        return $this->handleSuccessResponse("Successfully logged out");
    }

}