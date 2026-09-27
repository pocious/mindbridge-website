@extends('auth.layout')
@section('title', 'Choose a password')
@section('content')
  <h1>Choose a password</h1>
  <p class="lead">At least 10 characters, with letters and numbers.</p>
  <form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username">
    <label for="password">New password</label>
    <input id="password" type="password" name="password" required autofocus autocomplete="new-password">
    <label for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
    <button type="submit">Save password</button>
  </form>
@endsection
