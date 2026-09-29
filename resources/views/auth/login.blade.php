@extends('auth.layout')
@section('title', 'Sign in')
@section('content')
  <h1>Sign in</h1>
  <p class="lead">Use the email address the firm registered for you.</p>
  <form method="POST" action="{{ route('login') }}">
    @csrf
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
    <label for="password">Password</label>
    <input id="password" type="password" name="password" required autocomplete="current-password">
    <div class="row">
      <label><input type="checkbox" name="remember"> Keep me signed in</label>
      <a href="{{ route('password.request') }}">Forgot password?</a>
    </div>
    <button type="submit">Sign in</button>
  </form>
  <p class="switch">New here? <a href="{{ route('register') }}">Create an account</a></p>
@endsection
