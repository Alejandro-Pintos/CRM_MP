<?php

protected function unauthenticated($request, \Illuminate\Auth\AuthenticationException $exception)
{
    return response()->json(['message' => 'No autenticado'], 401);
}
