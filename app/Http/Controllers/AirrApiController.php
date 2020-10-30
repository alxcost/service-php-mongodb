<?php

namespace App\Http\Controllers;

use App\AirrRearrangement;
use App\AirrRepertoire;
use App\AirrUtils;
use App\Info;
use Illuminate\Http\Request;

class AirrApiController extends Controller
{
    public function index()
    {
        $response['result'] = 'success';

        $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return response($return_response)->header('Content-Type', 'application/json');
    }

    public function info()
    {
        $response['name'] = 'airr-api-ireceptor';
        $response['version'] = '0.1.0';
        $response['last_update'] = Info::getLastUpdate();

        $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return response($return_response)->header('Content-Type', 'application/json');
    }

    public function swagger()
    {
    }

    public function airr_repertoire(Request $request)
    {
        // /repertoire entry point that resolves an AIRR API repertoire query request and
        //    currently returns an iReceptor API response
        // treat no parameters as an empty JSON file
        if (sizeof($request->all()) == 0) {
            $params = json_encode('{}');
        } else {
            $params = $request->json()->all();
        }
        $response = [];
        $error = json_last_error();
        if ($error) {
            //something went bad and Laravel cound't parse the parameters as JSON
            $response['message'] = 'Unable to parse JSON parameters:' . json_last_error_msg();

            return response($response, 400)->header('Content-Type', 'application/json');
        }

        //check non-filter parameters and return error if there is one
        $params_verify = AirrUtils::verifyParameters($params);
        if ($params_verify != null) {
            $response['message'] = 'Error in parameters: ' . $params_verify;

            return response($response, 400)->header('Content-Type', 'application/json');
        }
        $l = AirrRepertoire::airrRepertoireRequest($params);
        switch ($l) {
                case 'error':
                    $response = [];
                    $response['message'] = 'Unable to parse the filter.';
                    $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

                    return response($return_response, 400)->header('Content-Type', 'application/json');
                    break;
                case 'size_error':
                    $response = [];
                    $response['message'] = 'Invalid size parameter.';
                    $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

                    return response($return_response, 400)->header('Content-Type', 'application/json');
                    break;

                case 'from_error':
                    $response = [];
                    $response['message'] = 'Invalid from parameter.';
                    $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

                    return response($return_response, 400)->header('Content-Type', 'application/json');
                    break;

                default:
                    //check what kind of response we have, default to JSON
                    $response_type = 'json';
                    if (isset($params['format']) && $params['format'] != '') {
                        $response_type = strtolower($params['format']);
                    }
                    $response['Info']['Title'] = 'AIRR Data Commons API';
                    $response['Info']['description'] = 'API response for repertoire query';
                    $response['Info']['version'] = 1.3;
                    $response['Info']['contact']['name'] = 'AIRR Community';
                    $response['Info']['contact']['url'] = 'https://github.com/airr-community';

                    if (isset($params['facets'])) {
                        //facets have different formatting requirements
                        $response['Facet'] = AirrRepertoire::airrRepertoireFacetsResponse($l);
                    } else {
                        //regular response, needs to be formatted as per AIRR standard, as
                        //	iReceptor repertoires are flat collections in MongoDB
                        $response['Repertoire'] = AirrRepertoire::airrRepertoireResponse($l, $params);
                    }
                }
        $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return response($return_response)->header('Content-Type', 'application/json');
    }

    public function airr_repertoire_single($repertoire_id)
    {
        $repertoire = AirrRepertoire::airrRepertoireSingle($repertoire_id);
        $response['Info']['Title'] = 'AIRR Data Commons API';
        $response['Info']['description'] = 'API response for repertoire query';
        $response['Info']['version'] = 1.3;
        $response['Info']['contact']['name'] = 'AIRR Community';
        $response['Info']['contact']['url'] = 'https://github.com/airr-community';
        $response['Repertoire'] = AirrRepertoire::airrRepertoireResponseSingle($repertoire);

        $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return response($return_response)->header('Content-Type', 'application/json');
    }

    public function airr_rearrangement(Request $request)
    {
        // /repertoire entry point that resolves an AIRR API rearrangement query request and
        //    currently returns an iReceptor API response
        $params = $request->json()->all();

        $error = json_last_error();
        if ($error) {
            //something went bad and Laravel cound't parse the parameters as JSON
            $response['message'] = 'Unable to parse JSON parameters:' . json_last_error_msg();

            return response($response, 400)->header('Content-Type', 'application/json');
        }

        //check non-filter parameters and return error if there is one
        $params_verify = AirrUtils::verifyParameters($params);
        if ($params_verify != null) {
            $response['message'] = 'Error in parameters: ' . $params_verify . "\n";

            return response($response, 400)->header('Content-Type', 'application/json');
        }
        //check if we can optimize the ADC API query for our repository
        //  if so, go down optimizied query path
        if (AirrUtils::queryOptimizable($params, JSON_OBJECT_AS_ARRAY)) {
            return response()->streamDownload(function () use ($params) {
                AirrRearrangement::airrOptimizedRearrangementRequest($params, JSON_OBJECT_AS_ARRAY);
            });
        } else {
            $l = AirrRearrangement::airrRearrangementRequest($params, JSON_OBJECT_AS_ARRAY);
            switch ($l) {
                 case 'error':
                    $response = [];
                    $response['message'] = 'Unable to parse the filter.';
                    $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

                    return response($return_response, 400)->header('Content-Type', 'application/json');
                    break;
                 case 'size_error':
                    $response = [];
                    $response['message'] = 'Invalid size parameter.';
                    $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

                    return response($return_response, 400)->header('Content-Type', 'application/json');
                    break;

                 case 'from_error':
                    $response = [];
                    $response['message'] = 'Invalid from parameter.';
                    $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

                    return response($return_response, 400)->header('Content-Type', 'application/json');
                    break;

                 default:
                    //check what kind of response we have, default to JSON
                    $response_type = 'json';
                    if (isset($params['format']) && $params['format'] != '') {
                        $response_type = strtolower($params['format']);
                    }
                    if (isset($params['facets'])) {
                        $response = AirrUtils::airrHeader();

                        //facets have different formatting requirements
                        $response['Facet'] = AirrRearrangement::airrRearrangementFacetsResponse($l);

                        return response($response)->header('Content-Type', 'application/json');
                    } else {
                        //regular response, needs to be formatted as per AIRR standard, as
                        //  iReceptor repertoires are flat collections in MongoDB
                        //$response['result'] = Sequence::airrRearrangementResponse($l);
                        return response()->streamDownload(function () use ($l, $response_type, $params) {
                            AirrRearrangement::airrRearrangementResponse($l, $response_type, $params);
                        });
                    }
                    break;
            }
        }
    }

    public function airr_rearrangement_single($rearrangement_id)
    {
        $rearrangement = AirrRearrangement::airrRearrangementSingle($rearrangement_id);
        $response = AirrUtils::airrHeader();
        $return_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (isset($rearrangement[0])) {
            $response['Rearrangement'] = AirrRearrangement::airrRearrangementResponseSingle($rearrangement[0]);
        } else {
            $response['Rearrangement'] = [];
        }

        return response($response)->header('Content-Type', 'application/json');
    }
}
