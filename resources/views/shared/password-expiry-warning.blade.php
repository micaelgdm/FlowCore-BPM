@if (session('password_expiry_warning'))
    <div class="fc-password-expiry-warning" role="status">{{ session('password_expiry_warning') }}</div>
@endif
