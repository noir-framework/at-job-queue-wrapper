<?php

namespace Noir\At;

/**
 * The class that wraps the at binary. It is not feature complete as the
 * native at binary does offer more, but it does contain the commonly used
 * functions and is enough for my purposes.
 */
class Wrapper
{
    /** The path to the `at` binary. */
    protected static string $binary = '/usr/bin/at';

    /** Regex to get the vitals from the queue. */
    protected static string $queueRegex = '/^(\d+)\s+([\w\d\- :]+) (\w) ([\w-]+)$/';

    /** Set escape whether using the escapeshellcmd before add command. */
    protected static bool $escape = true;

    /**
     * Location to pipe the output of at commands to.
     *
     * I need to combine STDERR and STDOUT for my machine as when adding a new
     * job `at` responds over STDERR because it wants to warn me
     * "warning: commands will be executed using /bin/sh". When getting a list
     * of jobs in the queue however it comes back over STDOUT.
     *
     * Combining the two allows me to use the same pipe command for both types
     * of interaction with `at`. I think it is also the safest way of
     * accommodating users who do not have the problem of warning being
     * triggered when adding a new job.
     */
    protected static string $pipeTo = '2>&1';

    /** Switches/arguments that at uses on the `at` command. */
    protected static array $atSwitches = [
        'queue' => '-q',
        'list_queue' => '-l',
        'file' => '-f',
        'remove' => '-d',
    ];

    /**
     * @param string $command The current command
     * @param string $time Please see `man at`
     * @param string|null $queue Please look at a-zA-Z and see `man at`
     * @return Job
     * @throws JobAddException
     * @uses self::addCommand
     */
    public static function cmd(string $command, string $time, ?string $queue = null): Job
    {
        return self::addCommand($command, $time, $queue);
    }

    /**
     * @param string $file Full path to the file to be executed
     * @param string $time Please see `man at`
     * @param string|null $queue Please look at a-zA-Z and see `man at`
     * @return Job
     * @throws JobAddException
     * @uses self::addFile
     */
    public static function file(string $file, string $time, ?string $queue = null): Job
    {
        return self::addFile($file, $time, $queue);
    }

    /**
     * @param string|null $queue Please look at a-zA-Z and see `man at`
     * @return array
     * @uses self::listQueue
     */
    public static function lq(?string $queue = null): array
    {
        return self::listQueue($queue);
    }

    /**
     * Set escape param.
     *
     * @param bool $escape The escaped command
     * @return self
     */
    public function setEscape(bool $escape): self
    {
        self::$escape = $escape;

        return $this;
    }

    /**
     * Add a job to the `at` queue.
     *
     * @param string $command The current command
     * @param string $time Please see `man at`
     * @param string|null $queue Please look at a-zA-Z and see `man at`
     * @return Job
     * @throws JobAddException
     */
    public static function addCommand(string $command, string $time, ?string $queue = null): Job
    {
        if (true === self::$escape) {
            $command = self::escape($command);
            $time = self::escape($time);
        }
        $exec_string = "echo '$command' | " . self::$binary;
        if (null !== $queue) {
            $exec_string .= ' ' . self::$atSwitches['queue'] . " $queue[0]";
        }
        $exec_string .= " $time ";

        return self::addJob($exec_string);
    }

    /**
     * Add a file job to the `at` queue.
     *
     * @param string $file Full path to the file to be executed
     * @param string $time Please see `man at`
     * @param string|null $queue Please look at a-zA-Z and see `man at`
     * @return Job
     * @throws JobAddException
     */
    public static function addFile(string $file, string $time, ?string $queue = null): Job
    {
        if (true === self::$escape) {
            $file = self::escape($file);
            $time = self::escape($time);
        }
        $exec_string = self::$binary . ' ' . self::$atSwitches['file'] . " $file";
        if (null !== $queue) {
            $exec_string .= ' ' . self::$atSwitches['queue'] . " $queue[0]";
        }
        $exec_string .= " $time ";

        return self::addJob($exec_string);
    }

    /**
     * Return a list of the jobs currently in the queue. If you do not specify
     * a queue to look at then it will return all jobs in all queues.
     *
     * @param string|null $queue Please look at a-zA-Z and see `man at`
     * @return array
     */
    public static function listQueue(?string $queue = null): array
    {
        $exec_string = self::$binary . ' ' . self::$atSwitches['list_queue'];
        if (null !== $queue) {
            $exec_string .= ' ' . self::$atSwitches['queue'] . " $queue[0]";
        }
        $result = self::exec($exec_string);

        return self::transform($result, 'queue');
    }

    /**
     * Remove a job by job number.
     *
     * @param int|string $job_number The current job number
     * @return void
     * @throws JobNotFoundException
     */
    public static function removeJob(int|string $job_number): void
    {
        if (true === self::$escape) {
            $job_number = self::escape((string)$job_number);
        }
        $exec_string = self::$binary . ' ' . self::$atSwitches['remove'] . " $job_number";
        $output = self::exec($exec_string);
        if (count($output)) {
            throw new JobNotFoundException("The job number $job_number could not be found");
        }
    }

    /**
     * Add a job to the at queue and return.
     *
     * @param string $job_exec_string The job execution string
     * @return Job
     * @throws JobAddException
     */
    protected static function addJob(string $job_exec_string): Job
    {
        $output = self::exec($job_exec_string);
        $job = self::transform($output);
        $failedMessageFormat = 'The job has failed to be added to the queue. Exec command: %s';
        $failedMessage = sprintf($failedMessageFormat, $job_exec_string);
        if (!count($job)) {
            throw new JobAddException($failedMessage);
        }

        return reset($job);
    }

    /**
     * Transform the output of `at` into an array of objects.
     *
     * @param array $output_array The output with array
     * @param string $type Is this an add or list we are transforming?
     * @return Job[]
     * @uses Job
     */
    protected static function transform(array $output_array, string $type = 'add'): array
    {
        $jobs = [];

        // Get the appropriate regex class property for the type
        // of `at` switch/command being run at this point in time.
        $regex = $type . 'Regex';
        $regex = self::$$regex;

        $map = $type . 'Map';
        $map = self::$$map;

        foreach ($output_array as $line) {
            $matches = [];
            preg_match($regex, $line, $matches);
            if (count($matches) > count($map)) {
                $jobs[] = self::mapJob($matches, $map);
            }
        }

        return $jobs;
    }

    /**
     * Map the details matched with the regex to descriptively named properties
     * in a new Job object.
     *
     * @param array $details The details about job description
     * @param array $map The mapped job array
     * @return Job
     */
    protected static function mapJob(array $details, array $map): Job
    {
        $Job = new Job();
        foreach ($details as $key => $detail) {
            if (isset($map[$key])) {
                $Job->{$map[$key]} = $detail;
            }
        }

        return $Job;
    }

    /**
     * Escape a string that will be passed to exec.
     *
     * @param string $string The executed command
     * @return string
     */
    protected static function escape(string $string): string
    {
        return escapeshellcmd($string);
    }

    /**
     * Run the command via exec() and return each line of the output as an
     * array.
     *
     * @param string $string The executed string
     * @return string[] Each line of output is an element in the array
     */
    protected static function exec(string $string): array
    {
        $output = [];
        $string .= ' ' . self::$pipeTo;
        exec($string, $output);

        return $output;
    }
}
