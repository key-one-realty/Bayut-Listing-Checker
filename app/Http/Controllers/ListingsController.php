<?php

namespace App\Http\Controllers;

use App\Mail\InactiveListingReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ListingsController extends Controller
{
    //
    protected $keyone_listing_xml_feed_link;
    protected $ch;

    protected $portfolio_manager_mail;

    public function __construct() {
        $this->keyone_listing_xml_feed_link = env("KEYONE_LISTING_XML_FEED");
        $this->ch = curl_init();
        $this->portfolio_manager_mail = env("PORTFOLIO_MANAGER_MAIL");
    }

    public function getClientXMLListings()
    {
        try {
            //code...
            curl_setopt($this->ch, CURLOPT_URL, $this->keyone_listing_xml_feed_link);
            curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->ch, CURLOPT_TIMEOUT, 300); 
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 150);

            $client_listing_xml_response = curl_exec($this->ch);
            $err = curl_error($this->ch);

            if ($err){
                // echo 'Curl error: ' . $err;

                // Close the cURL session
                curl_close($this->ch);
                throw new \Exception($err);
            } else {
                $xml = simplexml_load_string($client_listing_xml_response);
                // dd($xml);
                // echo $xml;

                // Close the cURL session
                curl_close($this->ch);
                return $xml;
            }
        } catch (\Throwable $th) {
            throw new \Exception($th->getMessage());
        }
    }

    public function getClientBayutListings(Request $request)
    {
        $company_slug = $request->company_slug;
        $pagination_count = $request->pagination_count;

        $client_bayut_Listings = $this->handleClientBayutListings($company_slug, $pagination_count);
        dd($client_bayut_Listings);
        
        return $client_bayut_Listings->hits;
    }

    public function handleClientBayutListings(string $company_slug, int $pagination_count)
    {
        try {
            curl_setopt_array($this->ch, [
                CURLOPT_URL => "https://bayut-com1.p.rapidapi.com/agencies/get-listings?agencySlug="  . $company_slug . "&hitsPerPage=" . $pagination_count . "&page=0",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "x-rapidapi-host: bayut-com1.p.rapidapi.com",
                    "x-rapidapi-key: 603aacc8fdmsh635c8ac97c4601ep162408jsn4e746117190f",
                    "Accept: application/json"
                ],
            ]);
    
            $rapid_bayut_listing_response = curl_exec($this->ch);
            $err = curl_error($this->ch);
    
            curl_close($this->ch);
    
            if ($err) {
                // echo "cURL Error #:" . $err;
                throw new \Exception($err);
            }
            //  else {
            //     echo $rapid_bayut_listing_response;
            // }
            return json_decode($rapid_bayut_listing_response);
        } catch (\Throwable $th) {
            throw new \Exception($th->getMessage());
        }
    }

    public function handleInactiveListings(int $feed_listings_count, int $inactive_listings_count, array $inactive_listings){

        try {
            //code...
                $inactive_listing_report = "";
            foreach ($inactive_listings as $listings) {
                # loop through the active listings and generate a report string for the email
                $listing_report = "
                Property Ref No: {$listings->Property_Ref_No} \n
                Property Name: {$listings->Tower_Name}, {$listings->Sub_Locality} \n
                Property Title: {$listings->Property_Title} \n
                ";

                $inactive_listing_report .= $listing_report;
            }

            $inactive_listing_mail_data = [
                "no_of_feeds_submitted" => $feed_listings_count,
                "no_of_inactive_feeds" => $inactive_listings_count,
                "inactive_listings_report" => $inactive_listing_report
            ];

            Mail::to($this->portfolio_manager_mail)->queue(new InactiveListingReport($inactive_listing_mail_data));
        } catch (\Throwable $th) {
            throw $th;
        }
        
    }


    public function findUnsuccessfulListings(Request $request){
        try {

            $company_slug = $request->company_slug;
            $pagination_count = $request->pagination_count;

            //attempt to get unsuccessful listings on bayut
            $client_listings = $this->getClientXMLListings();
            $client_bayut_listings = $this->handleClientBayutListings($company_slug, $pagination_count);

            $client_bayut_listings_collection = collect($client_bayut_listings->hits);

            // dd($client_bayut_listings_collection);

            // Extract reference IDs
            $client_bayut_listing_reference_id = $client_bayut_listings_collection->pluck("referenceNumber", "externalID")->toArray();
            // print_r($client_bayut_listing_reference_id);

            $updated_client_listings = [];

            $inactive_listing_count = 0;
            $active_listing_count = 0;

            $inactive_listings = [];
            $active_listings = [];

            foreach($client_listings as $listing) {
                $listing_ref_no = $listing->Property_Ref_No;
                // dd($listing_ref_no);
                $external_id = array_search($listing_ref_no, $client_bayut_listing_reference_id);

           
                $bayut_listing_data =  $client_bayut_listings_collection->where('referenceNumber', $listing_ref_no)->select("verification")->first();

                // dd($bayut_listing_data);
                
                $listing_on_bayut = in_array($listing_ref_no, $client_bayut_listing_reference_id);

                if ($listing_on_bayut){
                    $active_listing_count += 1;
                    array_push($active_listings, $listing);
                } else {
                    $inactive_listing_count += 1;
                    array_push($inactive_listings, $listing);
                }

                $updated_listing = [
                    // ...$listing,
                    "bayut_link" => "https://www.bayut.com/property/details-" . $external_id . ".html",
                    "verification" => $bayut_listing_data,
                    "reference_id" => $listing_ref_no,
                    "is_active" => $listing_on_bayut,
                ];

                array_push($updated_client_listings, $updated_listing);
            }

            $no_of_listings = count($updated_client_listings);

            if($inactive_listing_count > 0){
                $this->handleInactiveListings($no_of_listings, $inactive_listing_count, $inactive_listings);
            }

            return response()->json([
                "success" => true,
                "no_of_listings" => $no_of_listings,
                "inactive_listings_count" => $inactive_listing_count,
                // "inactive_listings" => $inactive_listings,
                // "active_listing" => $active_listings,
                "active_listings_count" => $active_listing_count,
                "updated_listings" => $updated_client_listings,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                "success" => false,
                "message" => "Error comparing listings: " . $th->getMessage()
            ]);
        }
    }
}
