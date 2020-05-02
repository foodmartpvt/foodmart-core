<?php

namespace App\Http\Controllers;

use App\Addon;
use App\Menu;
use Illuminate\Http\Request;
use ZipArchive;
use DB;
use Auth;
use App\BusinessSetting;
use CoreComponentRepository;

class AddonController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        CoreComponentRepository::instantiateShopRepository();
        return view('addons.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('addons.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($request->hasFile('addon_zip')) {
            // Create update directory.
            $dir = 'addons';
            if (!is_dir($dir))
                mkdir($dir, 0777, true);

            $path = $request->addon_zip->store('addons');
            $zipped_file_name = $request->addon_zip->getClientOriginalName();

            //dd(base_path('public/'.$path));
            //Unzip uploaded update file and remove zip file.
            $zip = new ZipArchive;
            $res = $zip->open(base_path('public/'.$path));
            if ($res === true) {
                $res = $zip->extractTo('addons');
                //dd($res);
                // if ($res === false) {
                //     dd('not extracted');
                //     $res = $zip->extractTo('addons');
                // }
                $zip->close();
                //unlink($path);
            }
            else {
                dd('could not open');
            }

            $unzipped_file_name = substr($zipped_file_name, 0, -4);

            $str = file_get_contents('addons/' . $unzipped_file_name . '/config.json');
            $json = json_decode($str, true);

            if (BusinessSetting::where('type', 'current_version')->first()->value >= $json['minimum_item_version']) {
                if(count(Addon::where('unique_identifier', $json['unique_identifier'])->where('version', $json['version'])->get()) == 0){
                    $addon = new Addon;
                    $addon->name = $json['name'];
                    $addon->unique_identifier = $json['unique_identifier'];
                    $addon->version = $json['version'];
                    $addon->activated = 1;
                    $addon->image = $json['addon_banner'];
                    $addon->save();

                    // Create new directories.
                    if (!empty($json['directory'])) {
                        //dd($json['directory'][0]['name']);
                        foreach ($json['directory'][0]['name'] as $directory) {
                            if (is_dir(base_path($directory)) == false){
                                mkdir(base_path($directory), 0777, true);

                            }else {
                                echo "error on creating directory";
                            }

                        }
                    }

                    // Create/Replace new files.
                    if (!empty($json['files'])) {
                        foreach ($json['files'] as $file)
                        if ($file['update_directory'] == 'routes/addon.php') {
                            $handle = fopen($file['root_directory'], "r");
                            if ($handle) {
                                while (($line = fgets($handle)) !== false) {
                                    $data = PHP_EOL.$line;
                                    $fp = fopen(base_path('routes/addon.php'), 'a');
                                    fwrite($fp, $data);
                                }
                                fclose($handle);
                            } else {
                                flash('can not read the file')->error();
                                return redirect()->route('addons.index');
                            }
                        }else {
                            copy(base_path($file['root_directory']), base_path($file['update_directory']));
                        }
                    }

                    // Run sql modifications
                    $sql_path = './addons/' . $unzipped_file_name . '/sql/update.sql';
                    if(file_exists($sql_path)){
                        DB::unprepared(file_get_contents($sql_path));
                    }



                    flash('Addon nstalled successfully')->success();
                    return redirect()->route('addons.index');

                }
                else {
                    flash('This addon is already installed')->error();
                    return redirect()->route('addons.index');
                }
            }
            flash('This version is not capable of installing Addons, Please update.')->error();
            return redirect()->route('addons.index');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Addon  $addon
     * @return \Illuminate\Http\Response
     */
    public function show(Addon $addon)
    {
        //
    }

    public function list()
    {
        //return view('backend.'.Auth::user()->role.'.addon.list')->render();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Addon  $addon
     * @return \Illuminate\Http\Response
     */
    public function edit(Addon $addon)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Addon  $addon
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Addon  $addon
     * @return \Illuminate\Http\Response
     */
    public function activation(Request $request)
    {
        $addon  = Addon::find($request->id);
        //$menu  = Menu::where('displayed_name', $addon->unique_identifier)->first();
        $addon->activated = $request->status;

        $addon->save();
        //$menu->save();

        // $data = array(
        //     'status' => true,
        //     'notification' => translate('addon_status_updated_successfully')
        // );
        // return $data;

        return 1;
    }
}
