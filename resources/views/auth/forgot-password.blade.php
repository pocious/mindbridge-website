@extends('auth.layout')
@section('title', 'Reset password')
@section('content')
  <h1>Reset your password</h1>
  <p class="lead">Enter your email and we’ll send you a link to choose a new password.</p>
  <form method="POST" action="{{ route('password.email') }}">
    @csrf
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
    <button type="submit">Send reset link</button>
  </form>
  <div class="row"><a href="{{ route('login') }}">← Back to sign in</a></div>
@endsection
