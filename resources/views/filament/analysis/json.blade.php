@php($data = $getState())
<details>
    <summary style="cursor: pointer; font-size: 0.875rem;">Show JSON</summary>
    <pre style="margin-top: 0.5rem; max-height: 24rem; overflow: auto; font-size: 0.75rem; padding: 0.75rem; border-radius: 0.5rem; background: rgba(127, 127, 127, 0.12);">{{ $data === null ? 'null' : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
</details>
