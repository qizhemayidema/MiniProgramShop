<?php

namespace app\http\middleware;

class LoginCheck
{
    public function handle($request, \Closure $next)
    {
        if (!session('admin.user_info')){
            return redirect(url('admin/login/index'));
        }
        return $next($request);
    }
}
