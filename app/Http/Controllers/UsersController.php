<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Department\DepartmentRepositoryContract;
use App\Repositories\Lead\LeadRepositoryContract;
use App\Repositories\Role\RoleRepositoryContract;
use App\Repositories\Setting\SettingRepositoryContract;
use App\Repositories\Task\TaskRepositoryContract;
use App\Repositories\User\UserRepositoryContract;
use Carbon\Carbon;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsersController extends Controller
{
    protected $users;
    protected $roles;
    protected $departments;
    protected $settings;

    public function __construct(
        UserRepositoryContract $users,
        RoleRepositoryContract $roles,
        DepartmentRepositoryContract $departments,
        SettingRepositoryContract $settings,
        TaskRepositoryContract $tasks,
        LeadRepositoryContract $leads
    ) {
        $this->users       = $users;
        $this->roles       = $roles;
        $this->departments = $departments;
        $this->settings    = $settings;
        $this->tasks       = $tasks;
        $this->leads       = $leads;
        $this->middleware('user.create', ['only' => ['create']]);
    }

    /**
     * @return mixed
     */
    public function index()
    {
        // dd($this->anyData());

        return view('users.index')->withUsers($this->users);
    }

    public function users()
    {
        return User::all();
    }

    public function anyData()
    {
        $users         = User::select('users.*');

        $dt = Datatables::of($users)
            ->addColumn('namelink', function ($users) {
                return '<a href="users/'.$users->id.'">'.$users->name.'</a>';
            });

        $actions = '';
        if (Auth::user()->can('user-delete')) {
            $actions .= '<form action="{{ route(\'users.destroy\', $id) }}" method="POST">';
        }
        if (Auth::user()->can('user-update')) {
            $actions .= '<a href="{{ route(\'users.edit\', $id) }}" class="btn btn-success btn-xs"> Edit</a> ';
        }
        if (Auth::user()->can('user-delete')) {
            $actions .= '<input type="hidden" name="_method" value="DELETE"><input type="submit" name="delete" value="Delete" class="btn btn-danger btn-xs" onClick="return confirm(\'Are you sure?\')"">{{csrf_field()}}</form>';
        }

        return $dt->addColumn('actions', $actions)
            ->rawColumns(['namelink', 'actions'])
            ->make(true);
    }

    /**
     * Json for Data tables.
     *
     * @param $id
     *
     * @return mixed
     */
    public function taskData($id)
    {
        $tasks = Task::select(
            ['id', 'title', 'created_at', 'deadline', 'user_assigned_id', 'client_id', 'status']
        )
            ->where('user_assigned_id', $id);

        return Datatables::of($tasks)
            ->addColumn('titlelink', function ($tasks) {
                return '<a href="'.route('tasks.show', $tasks->id).'">'.$tasks->title.'</a>';
            })
            ->editColumn('created_at', function ($tasks) {
                return $tasks->created_at ? with(new Carbon($tasks->created_at))
                    ->format('d/m/Y') : '';
            })
            ->editColumn('deadline', function ($tasks) {
                return $tasks->deadline ? with(new Carbon($tasks->deadline))
                    ->format('d/m/Y') : '';
            })
            ->editColumn('status', function ($tasks) {
                return 1 == $tasks->status ? '<span class="label label-success">Open</span>' : '<span class="label label-danger">Closed</span>';
            })
            ->editColumn('client_id', function ($tasks) {
                return $tasks->client->name;
            })
            ->rawColumns(['titlelink', 'status'])
            ->make(true);
    }

    /**
     * Json for Data tables.
     *
     * @param $id
     *
     * @return mixed
     */
    public function leadData($id)
    {
        $leads = Lead::select(
            ['id', 'title', 'created_at', 'contact_date', 'user_assigned_id', 'client_id', 'status']
        )
            ->where('user_assigned_id', $id);

        return Datatables::of($leads)
            ->addColumn('titlelink', function ($leads) {
                return '<a href="'.route('leads.show', $leads->id).'">'.$leads->title.'</a>';
            })
            ->editColumn('created_at', function ($leads) {
                return $leads->created_at ? with(new Carbon($leads->created_at))
                    ->format('d/m/Y') : '';
            })
            ->editColumn('contact_date', function ($leads) {
                return $leads->contact_date ? with(new Carbon($leads->contact_date))
                    ->format('d/m/Y') : '';
            })
            ->editColumn('status', function ($leads) {
                return 1 == $leads->status ? '<span class="label label-success">Open</span>' : '<span class="label label-danger">Closed</span>';
            })
            ->editColumn('client_id', function ($tasks) {
                return $tasks->client->name;
            })
            ->rawColumns(['titlelink', 'status'])
            ->make(true);
    }

    /**
     * Json for Data tables.
     *
     * @param $id
     *
     * @return mixed
     */
    public function clientData($id)
    {
        $client = Client::select(['id', 'name', 'primary_number', 'primary_email'])->where('user_id', $id);

        return Datatables::of($client)
            ->addColumn('clientlink', function ($client) {
                return '<a href="'.route('clients.show', $client->id).'">'.$client->name.'</a>';
            })
            ->addColumn('emaillink', function ($client) {
                return '<a href="mailto:'.$client->primary_email.'">'.$client->primary_email.'</a>';
            })
            ->editColumn('created_at', function ($client) {
                return $client->created_at ? with(new Carbon($client->created_at))
                    ->format('d/m/Y') : '';
            })
            ->rawColumns(['clientlink', 'emaillink'])
            ->make(true);
    }

    /**
     * @return mixed
     */
    public function create()
    {
        return view('users.create')
            ->withRoles($this->roles->listAllRoles())
            ->withDepartments($this->departments->listAllDepartments());
    }

    /**
     * @param StoreUserRequest $userRequest
     *
     * @return mixed
     */
    public function store(StoreUserRequest $userRequest)
    {
        $getInsertedId = $this->users->create($userRequest);

        return redirect()->route('users.index');
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public function show($id)
    {
        return view('users.show')
            ->withUser($this->users->find($id))
            ->withCompanyname($this->settings->getCompanyName())
            ->withTaskStatistics($this->tasks->totalOpenAndClosedTasks($id))
            ->withLeadStatistics($this->leads->totalOpenAndClosedLeads($id));
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public function edit($id)
    {
        return view('users.edit')
            ->withUser($this->users->find($id))
            ->withRoles($this->roles->listAllRoles())
            ->withDepartments($this->departments->listAllDepartments());
    }

    /**
     * @param $id
     * @param UpdateUserRequest $request
     *
     * @return mixed
     */
    public function update($id, UpdateUserRequest $request)
    {
        $this->users->update($id, $request);
        Session()->flash('flash_message', 'User successfully updated');

        return redirect()->back();
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public function destroy(Request $request, $id)
    {
        $this->users->destroy($request, $id);

        return redirect()->route('users.index');
    }
}
