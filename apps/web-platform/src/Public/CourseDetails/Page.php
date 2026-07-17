<?php

declare(strict_types=1);
use CourseHub\WebPlatform\Shared\Http\Response;

final class CourseDetailsPage
{
    public static function render(CourseDetailsViewModel $model): Response
    {
        $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        if($model->course===[]){return Response::html('<h1>Course not found</h1>',404);}
        $course=$model->course;$price=(float)($course['price']??0);$image=(string)($course['thumbnail_url']??'');
        $imageHtml=$image!==''?'<img src="'.$e($image).'" alt="" loading="eager">':'<div class="detail-image-fallback">CourseHub</div>';
        $curriculum='';$lessonCount=0;
        foreach($model->sections as $index=>$section){$lessons='';foreach((array)($section['lessons']??[]) as $lesson){$lessonCount++;$preview=(int)($lesson['is_preview']??0)===1?'<span>Preview</span>':'';$lessons.='<li><div><b>'.str_pad((string)$lessonCount,2,'0',STR_PAD_LEFT).'</b><p><strong>'.$e($lesson['title']??'Lesson').'</strong><small>'.$e($lesson['content_type']??'lesson').' · '.(int)($lesson['duration_minutes']??0).' min</small></p></div>'.$preview.'</li>';}$curriculum.='<article class="curriculum-section"><header><span>SECTION '.str_pad((string)($index+1),2,'0',STR_PAD_LEFT).'</span><h3>'.$e($section['title']??'Section').'</h3></header><ul>'.$lessons.'</ul></article>';}
        if($curriculum===''){$curriculum='<div class="detail-empty">Curriculum will be added by the instructor.</div>';}
        $action=$price>0?'<a class="buy-button" href="/student/cart?add='.(int)$course['id'].'">Buy once · NPR '.number_format($price,0).'</a>':'<a class="buy-button" href="/student/cart?add='.(int)$course['id'].'">Enroll free</a>';
        $html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$e($course['title']??'Course').' | CourseHub</title><link rel="stylesheet" href="/room-assets/Public/CourseDetails/page.css"></head><body class="detail-body">'
            .'<header class="detail-header"><a href="/">CourseHub</a><nav><a href="/courses">All courses</a><a href="/learn/sign-in">Student sign in</a></nav></header><main><section class="detail-hero"><div class="detail-copy"><a href="/courses">← Back to catalog</a><span>'.$e($course['category_name']??'Course').'</span><h1>'.$e($course['title']??'Untitled course').'</h1><p>'.$e($course['short_description']??'').'</p><div class="detail-meta"><div><small>Instructor</small><strong>'.$e($course['instructor_name']??'CourseHub instructor').'</strong></div><div><small>Level</small><strong>'.$e(ucfirst((string)($course['level']??'beginner'))).'</strong></div><div><small>Language</small><strong>'.$e($course['language']??'English').'</strong></div></div></div><aside class="detail-purchase"><div class="detail-image">'.$imageHtml.'</div><div class="purchase-body"><strong class="price">'.($price>0?'NPR '.number_format($price,0):'Free').'</strong>'.$action.'<ul><li>Lifetime course access</li><li>'.$lessonCount.' structured lessons</li><li>Progress tracking</li><li>Secure payment verification</li></ul></div></aside></section>'
            .'<section class="detail-content"><article class="detail-description"><span>ABOUT THIS COURSE</span><h2>What you will learn</h2><div>'.$e($course['full_description']??'Course details will be added soon.').'</div></article><aside class="detail-facts"><h3>Course information</h3><dl><div><dt>Duration</dt><dd>'.$e($course['duration']??'Self-paced').'</dd></div><div><dt>Access</dt><dd>Lifetime</dd></div><div><dt>Status</dt><dd>Published</dd></div></dl></aside></section>'
            .'<section class="curriculum"><div class="curriculum-heading"><span>CURRICULUM</span><h2>Course structure</h2><p>Preview lessons are available before enrollment. Full lessons unlock after verified payment.</p></div><div class="curriculum-list">'.$curriculum.'</div></section></main><footer class="detail-footer"><a href="/">CourseHub</a><span>Learn with structure.</span></footer></body></html>';
        return Response::html($html);
    }
}
