<?php
namespace Pentatrion\UploadBundle\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class InformativeException extends HttpException
{
  public function __construct(string $message = null, int $statusCode, \Throwable $previous = null, array $headers = [], ?int $code = E_NOTICE)
  {
    parent::__construct($statusCode, $message, $previous, $headers, $code);
  }
}