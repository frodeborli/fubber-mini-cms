<?php

use MiniCms\Ai\AgentInterface;
use MiniCms\Ai\ClaudeCodeAgent;
use MiniCms\Content;
use MiniCms\ContentStore;
use MiniCms\ImageProcessor;
use mini\Mini;
use mini\Lifetime;

require_once __DIR__ . '/src/components.php';

Mini::$mini->paths->static->addPath(__DIR__ . '/_static');

Mini::$mini->addService(Content::class, Lifetime::Singleton, function () {
    return new Content(Mini::$mini->root . '/_content');
});

Mini::$mini->addService(ContentStore::class, Lifetime::Singleton, function () {
    return new ContentStore(Mini::$mini->root . '/_content');
});

Mini::$mini->addService(ImageProcessor::class, Lifetime::Singleton, function () {
    return new ImageProcessor(Mini::$mini->root . '/uploads');
});

Mini::$mini->addService(AgentInterface::class, Lifetime::Singleton, function () {
    return new ClaudeCodeAgent(Mini::$mini->root);
});
