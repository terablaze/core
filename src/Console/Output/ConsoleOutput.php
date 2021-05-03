<?php

namespace TeraBlaze\Console\Output;

class ConsoleOutput implements OutputInterface
{
    public function out(string $message)
    {
        echo $message;
    }

    public function newline()
    {
        $this->out("\n");
    }

    public function display(string $message)
    {
        $this->newline();
        $this->out($message);
        $this->newline();
        $this->newline();
    }
}
