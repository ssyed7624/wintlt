<?php 

namespace App\Http\Controllers;

use App\Models\UserDetails\UserDetails;
use App\Models\CustomerDetails\CustomerDetails;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller{

	public function index(Request $request){

		$users = UserDetails::all();

		// $request->user($request->guard);
		// Auth::user();

		return response()->json($request->user($request->guard));
	}


	public function customers(Request $request){

		$users = CustomerDetails::all();

		// $request->user($request->guard);
		// Auth::user();

		return response()->json($request->user($request->guard));
	}
}