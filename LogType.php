<?php

namespace Sylphian\Library;

enum LogType: string
{
	case INFO = 'info';
	case WARNING = 'warning';
	case ERROR = 'error';
	case DEBUG = 'debug';
}
