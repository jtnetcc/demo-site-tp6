<?php

namespace app\command;

use app\model\PlayUrlCache;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class CleanupPlayUrlCache extends Command
{
    protected function configure(): void
    {
        $this->setName('playback:cleanup')->setDescription('Clean expired playback URL cache records');
    }

    protected function execute(Input $input, Output $output): int
    {
        $count = PlayUrlCache::where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
        $output->writeln('Cleaned playback cache records: ' . $count);

        return 0;
    }
}
