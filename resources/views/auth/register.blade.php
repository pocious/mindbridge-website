@extends('auth.layout')
@section('title', 'Create an account')
@section('content')
  <h1>Create an account</h1>
  <p class="lead">Ask the firm for access. A partner or the administrator will approve your account, and we’ll email you when you can sign in.</p>
  <form method="POST" action="{{ route('register') }}">
    @csrf
    <label for="signup_as">I am</label>
    <select id="signup_as" name="signup_as" required>
      <option value="client" @selected(old('signup_as', 'client') === 'client')>A client of the firm</option>
      <option value="staff" @selected(old('signup_as') === 'staff')>A member of the firm’s staff</option>
    </select>
    <label for="name">Full name</label>
    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
    <label for="phone">Phone (optional)</label>
    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel">
    <label for="organisation">Company or organisation (optional)</label>
    <input id="organisation" type="text" name="organisation" value="{{ old('organisation') }}" autocomplete="organization">
    <label for="password">Password</label>
    <input id="password" type="password" name="password" required autocomplete="new-password">
    <p class="hint">At least 10 characters, with letters and numbers.</p>
    <label for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
    <button type="submit">Request account</button>
  </form>
  <p class="switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
