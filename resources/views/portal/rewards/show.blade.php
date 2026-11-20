<x-portal.layout :title="$reward->title">
    <livewire:portal.reward-detail :reward="$reward" :key="'reward-detail-'.$reward->id" />
</x-portal.layout>
