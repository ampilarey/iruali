@props(['model', 'actions' => []])

<div class="flex gap-2">
    @if(in_array('edit', $actions) && auth()->user()->can('update', $model))
        <a href="{{ route($model->getTable() . '.edit', $model) }}" 
           class="btn btn-primary btn-sm">
            Edit
        </a>
    @endif

    @if(in_array('delete', $actions) && auth()->user()->can('delete', $model))
        <form action="{{ route($model->getTable() . '.destroy', $model) }}" 
              method="POST" class="inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm" 
                    onclick="return confirm('Are you sure?')">
                Delete
            </button>
        </form>
    @endif

    @if(in_array('approve', $actions) && auth()->user()->can('approve', $model))
        <form action="{{ route($model->getTable() . '.approve', $model) }}" 
              method="POST" class="inline">
            @csrf
            <button type="submit" class="btn btn-success btn-sm">
                Approve
            </button>
        </form>
    @endif

    @if(in_array('reject', $actions) && auth()->user()->can('reject', $model))
        <form action="{{ route($model->getTable() . '.reject', $model) }}" 
              method="POST" class="inline">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm">
                Reject
            </button>
        </form>
    @endif

    @if(in_array('cancel', $actions) && auth()->user()->can('cancel', $model))
        <form action="{{ route($model->getTable() . '.cancel', $model) }}" 
              method="POST" class="inline">
            @csrf
            <button type="submit" class="btn btn-secondary btn-sm" 
                    onclick="return confirm('Are you sure you want to cancel this order?')">
                Cancel
            </button>
        </form>
    @endif
</div> 