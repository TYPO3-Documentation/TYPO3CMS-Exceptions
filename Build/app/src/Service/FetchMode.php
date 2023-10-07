<?php

namespace T3DOCS\ExceptionCodes\Service;

enum FetchMode: string
{
    case All = 'all';
    case Missing = 'missing';
}
