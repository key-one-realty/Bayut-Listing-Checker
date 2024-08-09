<x-mail::message>
# Portfolio Listing Service

Dear Portolio Manager,

Your submitted feed contains:
{{ $no_of_feeds_submitted }} listings, <br />
{{ $no_of_inactive_feeds }} listings are inactive, <br />

View the Inactive listings below:

{{ $inactive_listings_report }}

<x-mail::button :url="'https://www.bayut.com/profolio/signin'">
Open Bayut Profolio
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
