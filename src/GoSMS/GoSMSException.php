<?php
namespace SMS\GoSMSException;

class GoSMSException extends \Exception
{
}

class Another extends GoSMSException
{
}

class InvalidCredentials extends GoSMSException
{
}

class TokenExpired extends GoSMSException
{
}

class ServerError extends GoSMSException
{
}

class AccessDenied extends GoSMSException
{
}

class MessageNotFound extends GoSMSException
{
}

class JSONAPIProblem extends GoSMSException
{
}

class InvalidFormat extends GoSMSException
{
}

class InvalidChannel extends GoSMSException
{
}
