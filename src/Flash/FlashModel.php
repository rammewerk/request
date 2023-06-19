<?php

namespace Rammewerk\Component\Request\Flash;

class FlashModel {

    public string $type;
    public string $message;




    public function __construct(FlashTypeEnum $type, string $message) {
        $this->type = $type->value;
        $this->message = $message;
    }


}