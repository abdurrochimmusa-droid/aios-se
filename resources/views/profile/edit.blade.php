@extends('layouts.app')

@section('title', 'Profil')
@section('subtitle', 'Kelola nama, email, dan kata sandi akun Anda.')

@section('content')
<div class="flex max-w-2xl flex-col gap-4">
 <div class="rounded-md border border-zinc-200 bg-white p-4 ">
 <div class="max-w-xl">
 @include('profile.partials.update-profile-information-form')
 </div>
 </div>

 <div class="rounded-md border border-zinc-200 bg-white p-4 ">
 <div class="max-w-xl">
 @include('profile.partials.update-password-form')
 </div>
 </div>

 <div class="rounded-md border border-zinc-200 bg-white p-4 ">
 <div class="max-w-xl">
 @include('profile.partials.delete-user-form')
 </div>
 </div>
</div>
@endsection
