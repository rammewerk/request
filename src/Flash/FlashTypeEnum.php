<?php

namespace Rammewerk\Component\Request\Flash;

enum FlashTypeEnum: string {

    case SUCCESS = 'success';
    case ERROR = 'error';
    case INFO = 'info';
    case WARNING = 'warning';
    case NOTIFY = 'notify';

}
