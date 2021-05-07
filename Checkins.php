<?php namespace Thienvu\Comaydorm\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Lang;
use October\Rain\Support\Facades\Url;
use Thienvu\Comaydorm\Classes\Helper;
use Thienvu\Comaydorm\Models\Card;
use Thienvu\Comaydorm\Models\Checkin;
use Thienvu\Comaydorm\Models\Student;

class Checkins extends Controller
{
    public $implement = ['Backend\Behaviors\ListController'];

    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Thienvu.Comaydorm', 'thienvu_comaydorm', 'thienvu_comaydorm_menu_checkins');
    }

    public function update($recordId = null, $context = null)
    {
        return $this->asExtension('FormController')->preview($recordId, $context);
    }

    public function apiStore()
    {
        $cardNum = null;
        $rfidSerial = null;

        if (isset(post()['card_num']) && !is_null(post()['card_num'])) {
            $cardNum = post()['card_num'];
        }
        if (isset(post()['rfid_serial']) && !is_null(post()['rfid_serial'])) {
            $rfidSerial = post()['rfid_serial'];
        }

        if (!$cardNum && !$rfidSerial) {
            return Helper::makeJsonResponse(400, "Please provide card_num or rfid_serial in body");
        }

        if ($rfidSerial) {
            $card = Card::where('rfid_serial', $rfidSerial)->first();
        } else {
            $card = Card::where('card_num', $cardNum)->first();
        }
        if (!$card) {
            return Helper::makeJsonResponse(422, "Invalid card");
        }

        $student = Student::where('card_id', $card->id)->first();
        if (!$student) {
            return Helper::makeJsonResponse(422, "Invalid card");
        }

        $lastCheckin = Checkin::where('student_id', $student->id)->orderBy("created_at", 'DESC')->first();

        $checkin = new Checkin;
        if (!$lastCheckin || $lastCheckin->attributes['type'] == "O") {
            $checkin->type = 'I';
        } else {
            $checkin->type = 'O';
        }
        $checkin->student_id = $student->id;
        $checkin->save();

        $ret = ['name' => $student->name, 'dob' => DateTime::createFromFormat('Y-m-d', $student->dob)->format('d/m/Y'), 'school' => $student->school->name, 'room' => $student->room->name, 'avatar' => $student->avatar !== null ? $student->avatar->getPath() : null, 'checkinType' => $checkin->attributes['type']];
        return Helper::makeJsonResponse(201, "Checkin successfully", $ret);
    }

    public function callAction($method, $parameters = false)
    {
        $action = 'api' . ucfirst($method);
        if (method_exists($this, $action) && is_callable(array($this, $action))) {
            return call_user_func_array(array($this, $action), $parameters);
        } else {
            return response()->json([
                'message' => 'Not Found',
            ], 404);
        }
    }

    private function getCheckinsBefore(Datetime $time)
    {
        $timeStr = $time->format('Y-m-d H:i');
        // traceLog($timeStr);
        $nearestCheckins = [];
        $students = Student::select('id')->get();
        foreach ($students as $student) {
            $id = $student->id;
            array_push($nearestCheckins, Checkin::where('student_id', $id)->where('created_at', '<', $timeStr)->max('created_at'));
        }

        // $checkins = Checkin::whereIn('created_at', $nearestCheckins)->get()->where('type', 'O');
        return Checkin::whereIn('created_at', $nearestCheckins)->get();
    }

    public function onDownloadOnTime()
    {
        $picktime = new DateTime();
        $picktime->setTime(16, 0, 0);
        $checkins = $this->getCheckinsBefore($picktime);

        $i = 0;
        $temp[$i++] = array('STT', 'Student', 'Type', 'Time', 'Room', 'School', 'RFID_Serial');
        foreach ($checkins as $checkin) {
            $studentName = $checkin->student->name;
            $type = Lang::get('thienvu.comaydorm::lang.checkin.types.' . $checkin->type);
            $date = new DateTime($checkin->created_at, new DateTimeZone('UTC'));
            $date->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
            $time = $date->format('H:i d/m/Y');
            $room = $checkin->student->room->name;
            $school = $checkin->student->school->name;
            $rfid_serial = $checkin->student->cards->rfid_serial;
            $temp[$i] = array($i, $studentName, $type, $time, $room, $school, $rfid_serial);

            $i++;
        }

        $filename = "DanhSachDiemDanhLuc23h00_" . (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('YmdHi') . ".csv";
        $filepath = temp_path() . "/" . $filename;

        $fp = fopen($filepath, 'wb');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
        foreach ($temp as $line) {
            // though CSV stands for "comma separated value"
            // in many countries (including France) separator is ";"
            fputcsv($fp, $line, ',');
        }
        fclose($fp);
        master them moiw
        return redirect(Url::route('downloadTemp', ['filename' => $filename]));
    }
}
