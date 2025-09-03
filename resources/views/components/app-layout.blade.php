@props(['title' => null, 'header' => null])
@include('layouts.app', ['title' => $title, 'header' => $header, 'slot' => $slot])
