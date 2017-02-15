<?php
namespace App;

use App\Database;

use App\Queries;
use App\UUID;
use App\User;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DatabaseSync {

    public static function syncAll() {
        
        $dbs = Database::all();
        $tables_time = Queries::AllTablesWithTimeConstraint(Auth::user()->time);
        $tables = Queries::AllTables();

        foreach ($dbs as $database) {

            if( $database->last_sync_date == null)
                DatabaseSync::doJob($tables, $database);
            else
                DatabaseSync::doJob($tables_time, $database);
            
        }
    }

    public static function doJob($tables, $database)
    {
        $data = array();
        $data['client'] = $database->db_name;
        $data['client_id'] = $database->uuid; 
        $data['last_sync_date'] = $database->last_sync_date;
        $data['sync_date'] = date('Y-m-d H:i:s');

        $all_tables_data = array();
        
        foreach($tables as $key => $value)
        {
            $table_data = DB::connection($database->db_name)->select($value);
            foreach($table_data as $td)
            {
                $td->client_id = $database->uuid;
                $td->o_id = $td->id;
            }

            $all_tables_data[$key] = $table_data;
        }
        $data['tables'] = $all_tables_data;        

        $ch = curl_init(User::find(1)->endpoint);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );                                                               

        $result = curl_exec($ch);

        if (preg_match("/OK/i", $result))
        {
            Storage::put(date('d-m-Y-H_i') . '.json', json_encode($data));
            $database->last_sync_date = date('Y-m-d H:i:s');
            $database->status = 1;
            $database->save();
        }
    }

}