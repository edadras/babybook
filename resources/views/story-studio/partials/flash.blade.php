@if (session('status'))
    <div class="flash" role="status">✓ {{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="flash error" role="alert">لطفاً خطاهای فرم را بررسی کنید.</div>
@endif
