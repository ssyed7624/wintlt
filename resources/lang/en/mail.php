<?php

    /*
    |--------------------------------------------------------------------------
    | To get all mail values in language
    |--------------------------------------------------------------------------
    */

return [
    'hi'  =>  'Hi,',
    'welcome_to'  =>  'Welcome to ',
    'regards' =>  'Regards,',
    'support_team'  =>  'Support Team,',
    'account_name'  =>  'ClarityTTS',
    //mail content update process - phase1
    'dear_valued_customer' => 'Dear :customerName,',
    'greetings_message' =>  'Greetings from :parentAccountName!',
    'thank_you_contacting'  =>  'Thank you for contacting us.',
    'our_support_team_contact'  =>  'Our Support Team is always ready to assist you. Please feel free to call our helpline at :parentAccountPhoneNo for any assistance or feedback.',
    'please_feel_free_contact'  =>  'Please feel free to call our helpline at :parentAccountPhoneNo for any clarifications.',
    'we_look_forward'   =>  'We look forward to working with you.',
    'we_will_be_reviewing_request'    =>  'We will be reviewing this request shortly and look forward to working with you.',
    'we_will_get_back_you_shortly'    => 'We will get back to you with an update shortly.',

    //send password
    'send_as_email_password_common_content1'  =>  'You recently requested to reset your password hence as per your request the password has been reset as <h3>:password</h3>',
    'send_password_subject' => 'Password Information',
    'send_agent_activation_subject' => 'Agent Activated Successfully',
    'send_agent_creation_subject' => 'Agent Created Successfully',
    'send_agency_registration_subject' => 'Agency Registered Successfully',
    'send_agent_registration_subject' => 'Agent Registered Successfully',
    'send_agency_activation_subject' => 'Agency Activated Successfully',

    'send_agency_reject_subject' => 'Agency Rejected',
    'send_agent_reject_subject' => 'Agent Rejected',
    'send_agency_approve_subject' => 'Agency Approved Successfully',
    'send_test_mail'  => 'Send Test Mail',
    'agent_registration_common_content'    =>  'Thank you for registering with us. Your request for a New Agent has been received.',
    'agent_creation_common_content' =>  'Your request for a New Agent has been received.',
    'agent_creation_contact'    =>  'In case of any clarifications please contact us at :parentAccountPhoneNo.',
    'agency_registration_common_content'    =>  'Thank you for registering with us. To serve you better, we bring to you a wide range of travel benefits with best airfares from various consolidators across the globe, providing you the benefit to boost your sales and customer satisfaction.',
    'agency_registration_thank_you' =>  'Thank you again for your interest in :parentAccountName. We look forward to working with you.',

    //agency approve
    'agency_approve_secure_msg' => 'Please keep your username and password secure. You should change your password every two weeks. ',
    //agency reject
    'agency_reject_common_content1'  =>  'Thank you for taking the time to register with us. Our concerned team was impressed with the quality of your application and documentation.',
    'agency_reject_common_content2'  =>  'However at this moment we are unable to provide a login to you due to limited availability of your product.',
    'agency_reject_common_content3'  =>  'Please feel free to contact us in the future if you expand your line of service. We appreciate the time and effort that you dedicated to register, and we look forward to the possibility of working together at some time in the future.',
    'transaction_limit_update_mail' =>  'Transaction Limit Updated Successfully',
    'allowed_credit_submit_mail' =>  'Amended Credit Submitted Successfully',
    'allowed_credit_approve_mail' =>  'Amended Credit Approved Successfully',
    'allowed_credit_reject_mail' =>  'Agency Credit Amend Request Rejected',
    'temporary_topup_update_mail' =>  'Temporary Topup Submitted Successfully',
    'temporary_topup_approve_mail' =>  'Temporary Topup Approved Successfully',
    'temporary_topup_reject_mail' =>  'Temporary Topup Rejected',
    'deposit_update_mail'   =>  'Deposit Submitted Successfully',
    'deposit_approve_update_mail'   =>  'Deposit Approved Successfully',
    'deposit_reject_update_mail'   =>  'Deposit Rejected',
    'payments_update_mail'   =>  'Payment Submitted Successfully',
    'payments_approve_update_mail'   =>  'Payment Approved Successfully',
    'payments_reject_update_mail'   =>  'Payment Rejected',
    'pending_payment_approve_mail'   =>  'Pending Payment Approved Successfully',
    'pending_payment_reject_mail'   =>  'Pending Payment Rejected',
     //transaction invoice based mail texts
    'transaction_limit_common_text'   =>  'We are pleased to inform you that after evaluating your performance, we would be happy to add a credit  of :currency :dailyLimit',
    'transaction_limit_accounts_team'  =>   'Our accounts team is always ready to assist you. Please feel free to call them at :parentAccountPhoneNo for any assistance or feedback',

    //allowed credit
    'allowed_credit_mail_content'   =>  'We are pleased to inform that we have received the :creditOrDebit Request of :currency :amount by you.',
    'allowed_credit_approve_mail_content'   =>  'We are pleased to inform that we have received the :creditOrDebit Request of :currency :amount by you and approved the :creditOrDebit after reviewing.',
    'allowed_credit_reject_mail_content1'   =>  'Our concerned team has reviewed the :creditOrDebit Request of :currency :amount by you.  However, we regret to inform you that at this moment we are unable to provide you due to inconsistent performance.',
    'allowed_credit_reject_mail_content2' => 'You are welcome to upload the amount and use our services.',

    //temporary topup
    'temporary_topup_mail_content'   =>  'Your temporary top-up request has been received.',
    'temporary_topup_approve_mail_content'   =>  'We are pleased to inform that we have received your request for temporary top-up of :currency :amount and this request has been approved from our end.',
    'temporary_topup_reject_mail_content1'   =>  'Our concerned team has reviewed the agency performance. We regret to inform you that at this moment we are unable to provide you temporary top-up of :currency :amount.',
    'temporary_topup_reject_mail_content2'   =>  'You are welcome to upload the amount and use our services.', 

    //deposit submit mail
    'deposit_mail_content1'   =>  'Your deposit of :creditOrDebit Request :currency :amount has been received.',
    'deposit_mail_content2'   =>  'Your account will be updated shortly.',
    'deposit_mail_content3'   =>  'We thank you for your business.',
    'deposit_approve_mail_content1' =>  'We are pleased to inform that we have received your deposit of :creditOrDebit Request amount of :currency :amount and the same has been updated from our end.',
    'deposit_reject_mail_content1' =>  'We are pleased to inform that we have received your request of deposit :creditOrDebit :currency :amount. However this deposit has not been received at our end. Kindly provide us with further details or kindly check with your bank and update us.',

    //payments submit mail
    'payment_mail_content1'   =>  'Your payment of :creditOrDebit Request :currency :amount has been received.',
    'payment_mail_content2'   =>  'Your account will be updated shortly.',
    'payment_mail_content3'   =>  'We thank you for your business.',
    'payment_approve_mail_content1' =>  'We are pleased to inform that we have received your payment of :creditOrDebit Request :currency :amount and the same has been updated from our end.',
    'payment_reject_mail_content1' =>  'We are pleased to inform that we have received your request of payment :creditOrDebit Request :currency :amount. However the same has not been received at our end. Kindly provide us with further details or kindly check with your bank and update us.',

    //pending payments submit mail
    'pending_payment_approve_mail_content'   =>  'We are pleased to inform that we have received your request for payment :creditOrDebit Request :currency :amount. Our concerned team has reviewed and updated this request from our end.',
    'pending_payment_approve_kindly_mail_content' => 'Kindly check the details at your end.',
    'pending_payment_reject_mail_content'   =>  'We are pleased to inform that we have received your request for payment :creditOrDebit Request :currency :amount. Our concerned team has reviewed this request. We regret to inform you that we have not received the said payment.',

    //Invoice Sending
    'we_would_like_to_support'  =>  'We would like to thank you for your support.',
    'please_find_attachment'    =>  'Please find the attached invoice statement.',
    'incase_query_contact'    =>  'In case you have any queries, Please feel free to call our helpline at :parentAccountPhoneNo for any assistance or feedback.',
    //user activation
    'user_activation_common_content' =>    'We are pleased to advise you that your user has been activated.',
    //user rejection
    'user_rejection_common_content'    =>  'We regret to inform you due to incorrect information or missing information, user creation has been rejected.',
    'thank_you_travel_requirement'  =>  'Thank you for calling us for your travel requirements.',
    'special_customized_offer'      =>  'We have provided a special deal customized only for you.',

    'oneway_trip_type_details'      =>  ':tripType flight from :originDetails to :detinationDetails departing on :departureDateTime for :passengerCount passenger(s), :cabinClass class for :totalFare ',

    'return_trip_type_details'      =>  'Round trip flight from :originDetails to :detinationDetails departing on :departureDateTime and return on :arrivalDateTime for :passengerCount passenger(s), :cabinClass class for :totalFare ',
    
    'multi_city_trip_type_details'  =>  'Multi city request for :passengerCount passenger(s), :cabinClass class for :totalFare ',

    'click_here_expiry_text'        =>  'Click on this link to book your customized deal and below link will be expiry at :expiryTime',
    'click_here' => 'Click Here',

    // User Refereal
    'referral_content_1' => 'Hi, your friend :userName has invited you to sign up in :portalName',
    'referral_link' => 'Click this link to sign up in :portalName and enjoy exclusive benefits.',
    'referral_signup' => 'Sign Up soon..! This link will be active for :expiryTime only.',
    'referral_expiry_time' => 'This URL will be active for :expiryTime only.',
    'referral_support' => 'For further assistance please contact us at :supportEmail',
    'referral_kindly_use' => 'Kindly click this url :url and sigin in :portalName.',
    'referral_confirmation_link' => 'Your friend :userName has referred you to enjoy our exclusive offers. Our support team will contact you for further clarification',
    'referral_update_group' => 'As consulted with our support team, you are requested to login to your existing :portalName account and answer the questions to upgrade & enjoy our exclusive benefits. Kindly click this url :url and sigin in :portalName',
   
    'event_lcoga_subject' => 'Air Ticket Draw Registration / Password Update',
    'user_dear_valued' => 'Dear :userName,',
    'user_greetings_message' => 'Greetings from :portalName!',
    'event_thanking_registered' => 'Thank you for registering with us.',
    'event_lcoga_content' => 'You have been entered into the draw for a chance to win a return air ticket from Toronto to Colombo subject to Terms and Conditions.',
    'your_registered_email' => 'Your registered email-id is :userEmail',
    'user_event_registration_common_content' => 'To serve you better, we bring to you a wide range of travel benefits with best airfares from various consolidators across the globe for your satisfaction. Please click on this Button to reset password for your :legalName account.',
    'reset_link' => 'Please use this link to reset the password :url',
    'event_change_password_expiry' => 'This link will be active for :expiryTime only.',
    'user_registration_thank_you' => 'Thank you again for your interest in :portalName.',
    'user_registration_common_content'    =>  'Thank you for registering with us. To serve you better, we bring to you a wide range of travel benefits with best airfares from various consolidators across the globe for your satisfaction.',
];