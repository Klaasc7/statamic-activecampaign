<?php

namespace Lwekuiper\StatamicActivecampaign\Http\Controllers;

use Statamic\Facades\Site;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Statamic\Facades\Blueprint;
use Illuminate\Support\Facades\Log;
use Statamic\Http\Controllers\Controller;
use Stillat\Proteus\Support\Facades\ConfigWriter;

class ActiveCampaignController extends Controller
{
    public function edit(Request $request)
    {
        $site = $request->site ?? Site::selected()->handle();

        // $this->authorize('edit', $variables);

        $values = $this->preProcess('statamic.activecampaign', $site);

        $blueprint = $this->getBlueprint();

        $fields = $blueprint->fields()->addValues($values)->preProcess();

        $viewData = [
            'blueprint' => $blueprint->toPublishArray(),
            'action' => cp_route('activecampaign.update', ['site' => $site]),
            'values' => $fields->values(),
            'meta' => $fields->meta(),
            'site' => $site,
            'localizations' => Site::all()->map(fn ($localization) => [
                'handle' => $localization->handle(),
                'name' => $localization->name(),
                'active' => $localization->handle() === $site,
                'url' => cp_route('activecampaign.edit', ['site' => $localization->handle()]),
            ])->values()->all(),
        ];

        if ($request->wantsJson()) {
            return $viewData;
        }

        return view('statamic-activecampaign::edit', $viewData);
    }

    public function update(Request $request)
    {
        $site = $request->site ?? Site::selected()->handle();

        $blueprint = $this->getBlueprint();

        // Get a Fields object, and populate it with the submitted values.
        $fields = $blueprint->fields()->addValues($request->all());

        // Perform validation. Like Laravel's standard validation, if it fails,
        // a 422 response will be sent back with all the validation errors.
        $fields->validate();

        // Perform post-processing. This will convert values the Vue components
        // were using into values suitable for putting into storage.
        $data = $fields->process()->values()->toArray();
        // $data = $this->postProcess($fields->process()->values()->toArray());

        // Write the values back to the config file.
        ConfigWriter::write("statamic.activecampaign.sites.{$site}", $data);

        // ConfigWriter::writeMany('statamic.activecampaign', [
        //     'api_key' => $request->api_key,
        //     "sites.{$site}" => $data,
        // ]);

        return response()->json([
            'saved' => true,
        ]);
    }

    /**
     * Get the blueprint.
     *
     * @return Blueprint
     */
    private function getBlueprint()
    {
        return Blueprint::find('statamic-activecampaign::config');
    }

    protected function preProcess(string $handle, $site): array
    {
        $config = config($handle);

        return Arr::get($config, "sites.{$site}", []);
    }

}
