<?php namespace Thienvu\Comaydorm\Models;

use Model;

/**
 * Model
 */
class Checkin extends Model
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'thienvu_comaydorm_checkins';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public $belongsTo = [
        'student' => [
            'Thienvu\Comaydorm\Models\Student',
            'key' => 'student_id',
        ],
    ];

    public function scopeFilterByType($query, $types)
    {
        return $query->whereIn('type', $types);
    }

    public function scopeSearchByStudentName($query, $studentName)
    {
        traceLog("runnnn");
        traceLog($studentName, $query === null);
        $ids = [];
        $students = Student::whereRaw("first_name LIKE '%" . $studentName . "%' OR last_name LIKE '%" . $studentName . "%'")->get();
        traceLog($students);

        foreach ($students as $st) {
            array_push($ids, $st->id);
        }
        return $query->whereIn('student_id', $ids);
    }
}
