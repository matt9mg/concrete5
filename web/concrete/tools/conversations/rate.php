<?php defined('C5_EXECUTE') or die("Access Denied.");
if (Loader::helper('validation/numbers')->integer($_POST['cnvMessageID']) && $_POST['cnvMessageID'] > 0) {

	$ratingType = ConversationRatingType::getByHandle($_POST['cnvRatingTypeHandle']);
	$cnvMessageID = $_POST['cnvMessageID'];
	$commentRatingUserID = $_POST['commentRatingUserID'];
	$msg = ConversationMessage::getByID($cnvMessageID);
	$msg->rateMessage($ratingType, $commentRatingUserID);
}