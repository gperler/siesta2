<?php

namespace Siesta\Contract;

interface Comparable
{
    public function arePrimaryKeyIdentical(Comparable $entity);
}