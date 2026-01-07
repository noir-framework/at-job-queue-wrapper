<?php

declare(strict_types=1);

namespace Noir\At;

use DateMalformedStringException;
use DateTime;

class Job
{
    /** Data store for the job details. */
    protected array $data = [];

    /**
     * Magic method to set a value in the $data
     * property of the class.
     *
     * @param string $name The key of data array
     * @param mixed $value The value of data array
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Magic method to get a value in the $data property
     * of the class.
     *
     * @param string $name The key of data array
     * @return mixed
     * @throws UndefinedPropertyException
     */
    public function __get(string $name): mixed
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        $trace = debug_backtrace();
        throw new UndefinedPropertyException(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']
        );
    }

    /**
     * Magic method to check for the existence of an
     * index in the $data property of the class.
     *
     * @param string $name The key of data array
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Magic method to unset an index in the $data property
     * of the class.
     *
     * @param string $name The key of data array
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    /**
     * Remove this job from the queue.
     *
     * @return void
     * @throws JobNotFoundException
     */
    public function remove(): void
    {
        if (isset($this->job_number)) {
            Wrapper::removeJob((int)$this->job_number);
        }
    }

    /**
     * Get a DateTime object for date and time extracted from
     * the output of `at`.
     *
     * @param string $date The date string
     * @return DateTime A PHP DateTime object
     * @throws DateMalformedStringException
     * @example echo $job->date()->format('d-m-Y');
     *
     * @uses DateTime
     *
     */
    public function date(string $date): DateTime
    {
        return new DateTime($date);
    }
}
