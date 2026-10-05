<?php

namespace JPD_Order_Documents_Vendor\Sabberworm\CSS\Comment;

interface Commentable
{
    /**
     * @param array<array-key, Comment> $aComments
     *
     * @return void
     */
    public function addComments(array $aComments);

    /**
     * @return array<array-key, Comment>
     */
    public function getComments();

    /**
     * @param array<array-key, Comment> $aComments
     *
     * @return void
     */
    public function setComments(array $aComments);
}
