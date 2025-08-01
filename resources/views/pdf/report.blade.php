<!DOCTYPE html>
<html lang="en">

<head>
    <title>Referral Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin-bottom: 20px; }
        .section h3 { border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        .detail-row { margin: 10px 0; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
    </style>
</head>

<body>
    <div class="header">
        <h1>External Referral Report</h1>
        <p>Generated on: {{ date('F j, Y') }}</p>
    </div>

    <div class="section">
        <h3>Referral Information</h3>
        <div class="detail-row">
            <span class="label">Referral ID:</span>
            <span>{{ $referringIndication['referral_id'] ?? '#REF0001' }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Customer ID:</span>
            <span>{{ $referringIndication['customer_id'] ?? '12345' }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Priority:</span>
            <span>{{ $referringIndication['priority'] ?? 'High' }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Status:</span>
            <span>{{ $referringIndication['status'] ?? 'Open' }}</span>
        </div>
    </div>

    <div class="section">
        <h3>Referral Details</h3>
        <div class="detail-row">
            <span class="label">Reason:</span>
            <span>{{ $referringIndication['referral_reason'] ?? 'Sample referral reason for external provider' }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Condition:</span>
            <span>{{ $referringIndication['referral_condition'] ?? 'Patient requires specialized assessment and treatment from external provider.' }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Medical History:</span>
            <span>{{ $referringIndication['medical_history'] ?? 'No significant medical history reported.' }}</span>
        </div>
    </div>

    <div class="section">
        <h3>External Referral Details</h3>
        @if(isset($referralDetails) && count($referralDetails) > 0)
            @foreach($referralDetails as $detail)
                @if(isset($detail['external_referral']) && count($detail['external_referral']) > 0)
                    @foreach($detail['external_referral'] as $external)
                        <div class="detail-row">
                            <span class="label">Provider Name:</span>
                            <span>{{ $external['name'] ?? 'Sample External Provider' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Organization:</span>
                            <span>{{ $external['organization'] ?? 'External Healthcare Organization' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Specialty:</span>
                            <span>{{ $external['specialty'] ?? 'General Practice' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Email:</span>
                            <span>{{ $external['email'] ?? 'provider@external.com' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Phone:</span>
                            <span>{{ $external['phone'] ?? '+1-234-567-8900' }}</span>
                        </div>
                    @endforeach
                @endif
            @endforeach
        @else
            <div class="detail-row">
                <span class="label">Provider Name:</span>
                <span>Sample External Provider</span>
            </div>
            <div class="detail-row">
                <span class="label">Organization:</span>
                <span>External Healthcare Organization</span>
            </div>
            <div class="detail-row">
                <span class="label">Specialty:</span>
                <span>General Practice</span>
            </div>
        @endif
    </div>
</body>

</html>
