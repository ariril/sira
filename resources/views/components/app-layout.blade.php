@props(['title' => null, 'header' => null, 'suppressGlobalError' => false])
@include('layouts.app', ['title' => $title, 'header' => $header, 'slot' => $slot, 'suppressGlobalError' => $suppressGlobalError])
