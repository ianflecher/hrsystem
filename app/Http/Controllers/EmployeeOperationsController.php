<?php

namespace App\Http\Controllers;

use App\Support\PeopleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeOperationsController extends Controller
{
    public function selfService()
    {
        $id = PeopleAccess::employeeId();
        $employee = DB::table('employees as e')->join('users as u','e.user_id','=','u.user_id')->leftJoin('departments as d','e.department_id','=','d.department_id')->where('e.employee_id',$id)->select('e.*','u.full_name','u.email','d.department_name')->first();
        $payslips = DB::table('hr_payroll')->where('employee_id',$id)->orderByDesc('period_end')->limit(12)->get();
        $leaves = DB::table('leaves')->where('employee_id',$id)->orderByDesc('created_at')->limit(10)->get();
        $notifications = Schema::hasTable('employee_notifications') ? DB::table('employee_notifications')->where('employee_id',$id)->orderByDesc('created_at')->limit(10)->get() : collect();
        $corrections = Schema::hasTable('attendance_corrections') ? DB::table('attendance_corrections')->where('employee_id',$id)->orderByDesc('created_at')->limit(10)->get() : collect();
        return view('employee.operations.self-service', compact('employee','payslips','leaves','notifications','corrections'));
    }

    public function request(Request $request)
    {
        $id = PeopleAccess::employeeId();
        $data = $request->validate(['type'=>'required|in:coe,attendance_correction,document,profile_change','request_date'=>'nullable|date','details'=>'required|string|max:5000']);
        DB::table('employee_requests')->insert(array_merge($data,[
            'employee_id'=>$id,'status'=>'pending','created_at'=>now(),'updated_at'=>now()
        ]));
        $this->notify($id,'request_submitted','Request submitted','Your '.$data['type'].' request was submitted for review.');
        return back()->with('success','Request submitted successfully.');
    }

    public function attendanceCorrection(Request $request)
    {
        $id = PeopleAccess::employeeId();
        $data = $request->validate(['attendance_date'=>'required|date','requested_time_in'=>'nullable|date_format:H:i','requested_time_out'=>'nullable|date_format:H:i','reason'=>'required|string|max:2000']);
        DB::table('attendance_corrections')->insert(array_merge($data,['employee_id'=>$id,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]));
        $this->notify($id,'attendance_correction','Attendance correction submitted','Your attendance correction is awaiting review.');
        return back()->with('success','Attendance correction submitted.');
    }

    public function markRead(int $id)
    {
        $employeeId = PeopleAccess::employeeId();
        if (Schema::hasTable('employee_notifications')) DB::table('employee_notifications')->where('id',$id)->where('employee_id',$employeeId)->update(['read_at'=>now(),'updated_at'=>now()]);
        return back();
    }

    private function notify(int $employeeId,string $type,string $title,string $message): void
    {
        if (Schema::hasTable('employee_notifications')) DB::table('employee_notifications')->insert(['employee_id'=>$employeeId,'type'=>$type,'title'=>$title,'message'=>$message,'created_at'=>now(),'updated_at'=>now()]);
    }
}
