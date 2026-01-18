

<div class="p-8">

    @if ($status == "approved")

        <div class="my-4">
            
            <h2 style="font-weight: 900"><strong>Hi there,</strong></h2>
            <br>
            <br> <br>

            <div>
                We are pleased to inform you that your driver verification has been approved. You are now officially registered as a driver on our platform.
            </div>

            <div class="my-4">
                Thank you for choosing to work with us. We look forward to a successful partnership.
            </div>

        </div>
        
    @endif

    @if ($status == "resubmission")

        <div class="my-4">
            
            <h2 style="font-weight: 900"><strong>Hi there,</strong></h2>
            <br>
            <br> <br>

            <div>
                We have reviewed your driver verification submission and found that some details are missing or incorrect. Please resubmit your documents and information to complete the verification process.
            </div>

            <div class="my-4">
                If you have any questions or need assistance, please contact our support team.
            </div>

        </div>
        
    @endif

    @if ($status == "declined")

        <div class="my-4">
            
            <h2 style="font-weight: 900"><strong>Hi there,</strong></h2>
            <br>
            <br> <br>

            <div>
                We regret to inform you that your driver verification has been declined. Unfortunately, your submission did not meet our verification criteria.
            </div>

            <div class="my-4">
                If you believe this is an error or have any questions, please contact our support team for further assistance.
            </div>

        </div>
        
    @endif


    <div>
        Best Regards, <br/>
        Tec-Ride Team
    </div>
</div>
