<template>
  <div>
    <div class="flex items-center justify-between">
      <h1>Suspected Spam Form Submissions</h1>
      <button @click="deleteAll()" class="btn text-red-500 mr-2">Delete All</button>
    </div>

    <div class="my-4">
      <p>
        Below is a list of form submissions that may be spam. You can choose to <span class="text-green-500">release</span> them, allowing them to be treated as valid submissions, or <span class="text-red-500">delete</span> them permanently.
      </p>
    </div>

    <div class="card overflow-hidden p-0">
      <table data-size="sm" tabindex="0" class="data-table">
        <thead>
        <tr>
          <th>
            <span>Form Name</span>
          </th>
          <th>
            <span>Spam Score</span>
          </th>
          <th>
            <span>Preview</span>
          </th>
          <th>
            <span>Form</span>
          </th>
          <th>
          </th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="item in items" :key="item.id">
          <td>
            {{ item.data.name ?? 'Unknown' }}
          </td>
          <td>
            {{ item.spam_score }} / {{ item.threshold }}
          </td>
          <td>
              {{ (item.data.message ?? item.data).slice(0, 50) + '...' }}
          </td>
          <td>
            <a class="text-blue-400 underline" :href="cp_url('forms/' + item.form_slug)">{{ item.form_slug }}</a>
          </td>
          <td class="w-1/8">
            <div class="flex h-full">
              <a v-bind:href="'riffraff/show/' + item.id" class="btn text-blue-500 mr-2">View</a>
              <button @click="release(item.id)" class="btn text-green-500 mr-2">Release</button>
              <button @click="destroy(item.id)" class="btn text-red-500 mr-2">Delete</button>
            </div>
          </td>
        </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default ({
  props: {
    items: Array
  },
  methods: {
    release(id) {
      Statamic.$axios.post(cp_url('alt-design/riffraff/release/' + id)).then(res => {
        window.location.reload()
      }).catch(err => {
        // handle error
      })
    },
    destroy(id) {
      if (confirm('Are you sure?')) {
        Statamic.$axios.delete(cp_url('alt-design/riffraff/' + id)).then(res => {
          window.location.reload()
        }).catch(err => {
          // handle error
        })
      }
    },
    deleteAll() {
        if (confirm('Are you sure? This will delete ALL suspected spam entries.')) {
            Statamic.$axios.delete(cp_url('alt-design/riffraff/all')).then(res => {
                window.location.reload()
            }).catch(err => {
                // handle error
            })
        }
    }
  }
})
</script>
