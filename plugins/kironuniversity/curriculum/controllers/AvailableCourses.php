<?php namespace Kironuniversity\Curriculum\Controllers;

use Log;
use BackendMenu;
use Backend\Classes\Controller;
use Kironuniversity\Curriculum\Models\AvailableCourse;
use Carbon\Carbon;
use Kironuniversity\Curriculum\Models\Course;
use Kironuniversity\Curriculum\Models\Platform;
use Backend;

/**
* AvailableCourse Back-end Controller
*/
class AvailableCourses extends Controller
{
  public $implement = [
    'Backend.Behaviors.ListController'
  ];

  public $listConfig = 'config_list.yaml';

  public function __construct()
  {
    parent::__construct();
    BackendMenu::setContext('Kironuniversity.Curriculum', 'curriculum', 'availablecourses');
  }

  private function findCourse($courseData) {
    // Courses are uniquely identified by the course URL
    $course = AvailableCourse::where('url', $courseData['url'])->first();
    // It seems that URLs change sometimes. Test for slug name, too.
    if(is_null($course))
    {
      $course = AvailableCourse::where('slug', $courseData['slug'])->where('initiative', $courseData['initiative'])->first();
    }

    return $course;
  }

  private function saveCourse($course, $courseData) {
    // Courses are saved if they have at least a name and a URL.
    if($courseData['name'] && $courseData['url']) {
      $course->denomination = $courseData['name'];
      $course->url = $courseData['url'];
      $course->slug = $courseData['slug'];
      $course->short_name = $courseData['short_name'];
      $course->description = $courseData['description'];
      $course->long_description = $courseData['long_description'];
      $course->syllabus = $courseData['syllabus'];
      if(empty($course->syllabus)){
        $course->syllabus = '';
      }
      $course->initiative = $courseData['initiative'];
      $course->thumbnail = $courseData['thumbnail'];
      $course->start_date = ($courseData['start_date']);
      $course->exact_date_known = ($courseData['exact_date_known'] === true);
      $course->language = $courseData['language'];
      $course->video_intro = $courseData['video_intro'];
      $course->length = $courseData['length'];
      $course->certificate = $courseData['certificate'];
      $course->verified_certificate = $courseData['verified_certificate'];

      if($courseData['modified'] != 0)
      $course->updated_at = $courseData['modified'];
      if($courseData['created'] != 0)
      $course->created_at = $courseData['created'];

      $course->workload_min = $courseData['workload_min'];
      $course->workload_max = $courseData['workload_max'];
      $course->save();
    }
  }

  private function multiRequest($data, $options = array()) {

    // array of curl handles
    $curly = array();
    // data to be returned
    $result = array();

    // multi handle
    $mh = curl_multi_init();

    // loop through $data and create curl handles
    // then add them to the multi-handle
    foreach ($data as $id => $d) {

      $curly[$id] = curl_init();

      $url = (is_array($d) && !empty($d['url'])) ? $d['url'] : $d;
      curl_setopt($curly[$id], CURLOPT_URL,            $url);
      curl_setopt($curly[$id], CURLOPT_HEADER,         0);
      curl_setopt($curly[$id], CURLOPT_RETURNTRANSFER, 1);

      // post?
      if (is_array($d)) {
        if (!empty($d['post'])) {
          curl_setopt($curly[$id], CURLOPT_POST,       1);
          curl_setopt($curly[$id], CURLOPT_POSTFIELDS, $d['post']);
        }
      }

      // extra options?
      if (!empty($options)) {
        curl_setopt_array($curly[$id], $options);
      }

      curl_multi_add_handle($mh, $curly[$id]);
    }

    // execute the handles
    $running = null;
    do {
      curl_multi_exec($mh, $running);
    } while($running > 0);


    // get content and remove handles
    foreach($curly as $id => $c) {
      $result[$id] = curl_multi_getcontent($c);
      curl_multi_remove_handle($mh, $c);
    }

    // all done
    curl_multi_close($mh);

    return $result;
  }

  public function scrapeMammooc(){
    $urls = [];
    for($i=1;$i<=2; $i++){
      $urls[] = 'https://mammooc.org/courses.json?page='.$i;
    }

    $chunked = array_chunk($urls,30);
    $combined = '';
    $count = 0;
    foreach($chunked as $chunk){
      $results = $this->multiRequest($chunk);
      foreach($results as $result){
        if($result ==  '[]'){
          break 2;
        }
        $combined .= trim($result, '[]').',';
        $count++;
      }
    }
    $combined = '['.trim($combined,',').']';
    $data = json_decode($combined, true);
    dd($count, $data[0]);

  }

  public function scrapeCourses() {
    //TODO: Change this to some kiron-hosted endpoint or access the class-central db directly.
    $courseSourceUrl = 'http://class-central.wailord.lb2.eu/web/api.php';

    Log::info('Scraping new courses');

    if(AvailableCourse::orderBy('updated_at', 'desc')->first()){
      $latestModifiedDate = AvailableCourse::orderBy('updated_at', 'desc')->first()->updated_at->getTimestamp();
    }else{
      $latestModifiedDate = Carbon::createFromDate(1990, 1, 1)->getTimestamp();
    }


    // Download a list of all available courses.
    $pullUri = $courseSourceUrl . '?modified_after=' . $latestModifiedDate;

    $json = file_get_contents($pullUri);

    $courseList = json_decode($json, true);

    $updatedCourses = 0;
    foreach($courseList as $courseData) {
      $course = $this->findCourse($courseData);
      if(is_null($course)) {
        $course = new AvailableCourse;
      }

      $this->saveCourse($course, $courseData);
    }

    Log::info('Scraped new courses: '. count($courseList). ' updated courses.');
  }

  public function setDefaultString($model, $fields){
    foreach($fields as $field){
      if(empty($model->$field)){
        $model->$field = '';
      }
    }
  }

  public function copy($id){

    $avc = AvailableCourse::findorFail($id);
    $this->setDefaultString($avc,['long_description', 'syllabus', 'description']);

    if(!$avc->course_id){
      $platform = Platform::where('denomination', 'ilike', $avc->initiative)->first();
      if(!$platform){
        dd('Platform: '. $avc->initiative. ' is not in our database', $avc->initiative);
      }

      $a_fields = [
        'denomination' => $avc->denomination,
        'link' => $avc->url,
        'syllabus' => $avc->syllabus,
        'short_description' => $avc->description,
        'long_description' => $avc->long_description,
        'platform_id' => $platform->id,
        'weeks' => $avc->length,
        //'code' => $avc->id + 3000,
        'course_level_id' => 1,
      ];

      if($avc->certificate){
        $a_fields['certificate'] = 'paid';
      }else{
        $a_fields['certificate'] = 'none';
      }

      if($avc->workload_min){
        $a_fields['workload'] = $avc->workload_min;
      }else {
        $a_fields['workload'] = 0;
      }

      $a_fields['weeks'] = !empty($avc->length) ? $avc->length : 0;

      $date = new \Carbon\Carbon($avc->start_date);
      //dd($date->toDateString(), $avc->start_date);

      if($avc->start_date != '0000-00-00 00:00:00'){
        $a_fields['start_date'] = $date->toDateString();
      }

      $course = Course::create($a_fields);
      $avc->course_id = $course->id;
      $avc->save();
    }
    return Redirect(Backend::url('kironuniversity/curriculum/courses/update/'.$avc->course_id));
  }

}
